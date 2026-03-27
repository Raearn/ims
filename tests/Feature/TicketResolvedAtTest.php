<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketResolvedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_preserves_resolved_at_when_status_moves_from_resolved_to_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $ticket->update(['status' => 'Resolved', 'solution' => 'Done']);

        $resolvedAt = $ticket->fresh()->resolved_at;
        $this->assertNotNull($resolvedAt);

        $ticket->update(['status' => 'Closed']);

        $this->assertTrue($resolvedAt->equalTo($ticket->fresh()->resolved_at));
    }

    public function test_model_clears_resolved_at_when_reopened(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $ticket = Ticket::factory()->create(['status' => 'Resolved', 'solution' => 'Was fixed']);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $ticket->update(['status' => 'Open']);

        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_bulk_close_preserves_resolved_at(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $ticket->update(['status' => 'Resolved', 'solution' => 'Bulk close test']);
        $resolvedAt = $ticket->fresh()->resolved_at;
        $this->assertNotNull($resolvedAt);

        $this->patch(route('tickets.bulk.status'), [
            'ticket_ids' => [$ticket->id],
            'status' => 'Closed',
        ])->assertRedirect();

        $this->assertTrue($resolvedAt->equalTo($ticket->fresh()->resolved_at));
    }
}
