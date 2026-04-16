<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentOccurredAtTest extends TestCase
{
    use RefreshDatabase;

    private function categoryId(): int
    {
        $id = TicketCategory::query()->whereNull('parent_id')->value('id');
        $this->assertNotNull($id, 'No root ticket category found in seed data.');

        return (int) $id;
    }

    /** @return array<string, mixed> */
    private function basePayload(array $overrides = []): array
    {
        $tag = Tag::firstOrCreate(['name' => 'OccurredAtTestTag']);

        return array_merge([
            'title' => 'Test incident',
            'description' => null,
            'ticket_category_id' => $this->categoryId(),
            'priority' => 'Medium',
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
            'solution' => null,
            'attachment' => null,
            'incident_occurred_at' => null,
        ], $overrides);
    }

    public function test_store_saves_incident_occurred_at_when_provided(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $occurredAt = now()->subHours(3)->format('Y-m-d H:i:s');

        $this->post(route('tickets.store'), $this->basePayload([
            'incident_occurred_at' => $occurredAt,
        ]))->assertRedirect();

        $ticket = Ticket::where('title', 'Test incident')->latest()->firstOrFail();
        $this->assertNotNull($ticket->incident_occurred_at);
        $this->assertTrue($ticket->incident_occurred_at->isSameMinute(now()->subHours(3)));
    }

    public function test_store_requires_incident_occurred_at(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('tickets.store'), $this->basePayload([
            'incident_occurred_at' => null,
        ]))->assertSessionHasErrors('incident_occurred_at');
    }

    public function test_store_rejects_future_incident_occurred_at(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('tickets.store'), $this->basePayload([
            'incident_occurred_at' => now()->addHour()->toDateTimeString(),
        ]))->assertSessionHasErrors('incident_occurred_at');
    }

    public function test_update_saves_incident_occurred_at(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $ticket = Ticket::factory()->create(['status' => 'Open']);
        $tag = Tag::firstOrCreate(['name' => 'UpdateOccurredTag']);
        $ticket->tags()->sync([$tag->id]);

        $occurredAt = now()->subDay()->format('Y-m-d H:i:s');

        $this->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority' => $ticket->priority,
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
            'incident_occurred_at' => $occurredAt,
        ])->assertRedirect();

        $this->assertNotNull($ticket->fresh()->incident_occurred_at);
    }

    public function test_update_requires_incident_occurred_at_when_null_sent(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $ticket = Ticket::factory()->create([
            'status' => 'Open',
            'incident_occurred_at' => now()->subHour(),
        ]);
        $tag = Tag::firstOrCreate(['name' => 'ClearOccurredTag']);
        $ticket->tags()->sync([$tag->id]);

        $this->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority' => $ticket->priority,
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
            'incident_occurred_at' => null,
        ])->assertSessionHasErrors('incident_occurred_at');
    }
}
