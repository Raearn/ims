<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_mark_ticket_as_resolved(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => 'Resolved',
                'solution' => 'Test solution',
            ])
            ->assertRedirect();

        $this->assertSame('Resolved', $ticket->fresh()->status);
    }

    public function test_admin_can_close_a_ticket(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'Resolved']);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'Cancelled'])
            ->assertRedirect();

        $this->assertSame('Cancelled', $ticket->fresh()->status);
    }

    public function test_admin_can_set_any_valid_status(): void
    {
        $admin = $this->admin();

        foreach (['Open', 'In Progress', 'On Hold', 'Resolved', 'Cancelled'] as $status) {
            $ticket = Ticket::factory()->create();

            $data = ['status' => $status];
            if ($status === 'Resolved') {
                $data['solution'] = 'Test solution';
            }

            $this->actingAs($admin)
                ->patch(route('tickets.status.update', $ticket), $data)
                ->assertRedirect();

            $this->assertSame($status, $ticket->fresh()->status);
        }
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'Invalid'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_is_required(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), [])
            ->assertSessionHasErrors('status');
    }

    public function test_guest_cannot_update_ticket_status(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patch(route('tickets.status.update', $ticket), ['status' => 'Resolved'])
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_update_ticket_status(): void
    {
        $nonAdmin = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($nonAdmin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'Resolved'])
            ->assertForbidden();
    }

    public function test_admin_can_update_status_and_sync_handlers_together(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $handlers = User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => 'Resolved',
                'solution' => 'Test solution',
                'handler_ids' => $handlers->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        $this->assertSame('Resolved', $ticket->fresh()->status);
        $this->assertCount(2, $ticket->fresh()->handlers);
    }

    public function test_handler_ids_are_optional_on_status_update(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $ticket->handlers()->sync([$handler->id]);

        // Update status without providing handler_ids — handlers must remain untouched
        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'On Hold'])
            ->assertRedirect();

        $this->assertSame('On Hold', $ticket->fresh()->status);
        $this->assertCount(1, $ticket->fresh()->handlers);
    }

    public function test_changing_status_to_open_clears_handlers_when_handler_ids_omitted(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'On Hold']);
        $ticket->handlers()->sync([$handler->id]);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'Open'])
            ->assertRedirect();

        $this->assertSame('Open', $ticket->fresh()->status);
        $this->assertCount(0, $ticket->fresh()->handlers);
    }
}
