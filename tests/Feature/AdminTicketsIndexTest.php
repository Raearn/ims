<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_tickets_page_includes_tags_for_each_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::firstOrCreate(['name' => 'DDoS']);
        $ticket = Ticket::factory()->create();
        $ticket->tags()->attach($tag->id);

        $this->assertStringContainsString('admin/incidents', route('tickets'));

        $this->actingAs($admin)
            ->get(route('tickets'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets')
                ->where('tickets', fn ($tickets) => collect($tickets)->contains(
                    fn (array $t) => ($t['numericId'] ?? null) === $ticket->id
                        && in_array('DDoS', $t['tags'] ?? [], true)
                ))
            );
    }

    public function test_supervisor_can_visit_incidents_page(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->get(route('tickets'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tickets'));
    }

    public function test_supervisor_cannot_visit_users_page(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->get(route('users'))
            ->assertRedirect(route('supervisor.dashboard'));
    }
}
