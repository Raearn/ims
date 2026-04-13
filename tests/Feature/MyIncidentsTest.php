<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyIncidentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_my_incidents(): void
    {
        $this->get(route('my.incidents'))
            ->assertRedirect(route('login'));
    }

    public function test_technical_user_can_view_my_incidents_page(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MyIncidents'));
    }

    public function test_my_incidents_legacy_path_redirects_to_home(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->get(route('my.incidents'))
            ->assertRedirect(route('home'));
    }

    public function test_my_incidents_legacy_path_preserves_query_string(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->get(route('my.incidents', ['ticket_id' => 5]))
            ->assertRedirect(route('home', ['ticket_id' => 5]));
    }

    public function test_non_technical_user_cannot_access_my_incidents(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('my.incidents'))
            ->assertForbidden();
    }

    public function test_supervisor_cannot_access_my_incidents(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->get(route('my.incidents'))
            ->assertForbidden();
    }

    public function test_technical_user_sees_only_reported_or_handled_tickets(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);
        $other = User::factory()->create(['role' => 'technical']);

        $reported = Ticket::factory()->create(['user_id' => $technical->id]);
        $handledOnly = Ticket::factory()->create(['user_id' => $other->id]);
        $handledOnly->handlers()->attach($technical->id);
        $stranger = Ticket::factory()->create(['user_id' => $other->id]);

        $this->actingAs($technical)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyIncidents')
                ->has('tickets', 2)
                ->where('tickets', function ($tickets) use ($reported, $handledOnly, $stranger): bool {
                    $ids = collect($tickets)->pluck('numericId')->all();

                    return in_array($reported->id, $ids, true)
                        && in_array($handledOnly->id, $ids, true)
                        && ! in_array($stranger->id, $ids, true);
                }));
    }

    public function test_technical_detail_json_forbidden_for_unrelated_ticket(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);
        $ticket = Ticket::factory()->create();

        $this->actingAs($technical)
            ->getJson(route('my.incidents.detail-json', $ticket))
            ->assertForbidden();
    }

    public function test_technical_detail_json_ok_for_reported_ticket(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);
        $ticket = Ticket::factory()->create(['user_id' => $technical->id]);

        $this->actingAs($technical)
            ->getJson(route('my.incidents.detail-json', $ticket))
            ->assertOk()
            ->assertJsonPath('ticket.numericId', $ticket->id);
    }

    public function test_technical_detail_json_ok_for_handled_ticket(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);
        $other = User::factory()->create(['role' => 'technical']);
        $ticket = Ticket::factory()->create(['user_id' => $other->id]);
        $ticket->handlers()->attach($technical->id);

        $this->actingAs($technical)
            ->getJson(route('my.incidents.detail-json', $ticket))
            ->assertOk()
            ->assertJsonPath('ticket.numericId', $ticket->id);
    }
}
