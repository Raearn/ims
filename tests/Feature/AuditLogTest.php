<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_admin_can_access_audit_log_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('audit-log'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AuditLog'));
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('audit-log'))
            ->assertRedirect(route('login'));
    }

    public function test_supervisor_cannot_access_audit_log(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->get(route('audit-log'))
            ->assertRedirect();
    }

    public function test_technical_user_cannot_access_audit_log(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);

        $this->actingAs($technical)
            ->get(route('audit-log'))
            ->assertForbidden();
    }

    // ── Data shape ────────────────────────────────────────────────────────────

    public function test_page_returns_correct_inertia_props_shape(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['title' => 'Server down']);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AuditLog')
                ->has('activities.data')
                ->has('activities.current_page')
                ->has('activities.total')
                ->has('activities.links')
                ->has('filters')
                ->has('users')
            );
    }

    public function test_activity_row_has_expected_fields(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['title' => 'My Ticket']);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AuditLog')
                ->has('activities.data.0', fn ($row) => $row
                    ->has('id')
                    ->has('action')
                    ->has('oldValue')
                    ->has('newValue')
                    ->has('userName')
                    ->has('userId')
                    ->has('ticketId')
                    ->has('ticketTitle')
                    ->has('ticketTktId')
                    ->has('createdAt')
                    ->has('createdAtFormatted')
                )
            );
    }

    // ── Action filter ─────────────────────────────────────────────────────────

    public function test_action_filter_returns_only_matching_activities(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now()->subMinute(),
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'High',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log', ['action' => 'priority_changed']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action', 'priority_changed')
                ->where('activities.data.0.action', 'priority_changed')
            );
    }

    // ── User filter ───────────────────────────────────────────────────────────

    public function test_user_filter_returns_only_activities_for_that_user(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now(),
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'High',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log', ['user_id' => $admin->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.user_id', (string) $admin->id)
                ->where('activities.data.0.userId', $admin->id)
            );
    }

    // ── Date range filter ─────────────────────────────────────────────────────

    public function test_date_range_filter_excludes_out_of_range_activities(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $today = now()->toDateString();

        // This activity is 10 days ago — should NOT appear when filtering for today
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'Closed',
            'created_at' => now()->subDays(10),
        ]);

        // This activity is today — should appear
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'High',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log', ['from' => $today, 'to' => $today]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.from', $today)
                ->where('filters.to', $today)
                ->where('activities.data', fn ($data) =>
                    // status_changed (Closed) from 10 days ago must not be present
                    collect($data)->every(fn ($row) => ! ($row['action'] === 'status_changed' && $row['newValue'] === 'Closed'))
                )
            );
    }

    // ── Ticket filter ─────────────────────────────────────────────────────────

    public function test_ticket_filter_returns_only_activities_for_that_ticket(): void
    {
        $admin = $this->admin();
        $ticketA = Ticket::factory()->create();
        $ticketB = Ticket::factory()->create();

        TicketActivity::create([
            'ticket_id' => $ticketA->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now(),
        ]);

        TicketActivity::create([
            'ticket_id' => $ticketB->id,
            'user_id' => $admin->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'High',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit-log', ['ticket_id' => $ticketA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities.data', fn ($data) =>
                    // Every row must belong to ticketA, none to ticketB
                    collect($data)->every(fn ($row) => $row['ticketId'] === $ticketA->id)
                )
            );
    }

    // ── Pagination ────────────────────────────────────────────────────────────

    public function test_pagination_second_page_returns_correct_current_page(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        for ($i = 0; $i < 55; $i++) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,
                'action' => 'status_changed',
                'old_value' => 'Open',
                'new_value' => 'In Progress',
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('audit-log', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities.current_page', 2)
            );
    }
}
