<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Bulk Status ─────────────────────────────────────────────────────────

    public function test_admin_can_bulk_update_status(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create();
        $tickets = Ticket::factory()->count(3)->create(['status' => 'Open']);

        $this->actingAs($admin)
            ->patch(route('tickets.bulk.status'), [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
                'status' => 'In Progress',
                'handler_ids' => [$handler->id],
            ])
            ->assertRedirect();

        $tickets->each(fn ($t) => $this->assertSame('In Progress', $t->fresh()->status));
    }

    public function test_bulk_status_requires_ticket_ids(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('tickets.bulk.status'), ['status' => 'Cancelled'])
            ->assertSessionHasErrors('ticket_ids');
    }

    public function test_bulk_status_requires_valid_status(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('tickets.bulk.status'), [
                'ticket_ids' => [$ticket->id],
                'status' => 'InvalidStatus',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_bulk_status_to_open_clears_handlers(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create();
        $tickets = Ticket::factory()->count(2)->create(['status' => 'In Progress']);
        $tickets->each(fn ($t) => $t->handlers()->sync([$handler->id]));

        $this->actingAs($admin)
            ->patch(route('tickets.bulk.status'), [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
                'status' => 'Open',
                'handler_ids' => [],
            ])
            ->assertRedirect();

        $tickets->each(fn ($t) => $this->assertCount(0, $t->fresh()->handlers));
    }

    public function test_bulk_status_syncs_handlers_when_provided(): void
    {
        $admin = $this->admin();
        $tickets = Ticket::factory()->count(2)->create(['status' => 'Open']);
        $handlers = User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->patch(route('tickets.bulk.status'), [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
                'status' => 'In Progress',
                'handler_ids' => $handlers->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        $tickets->each(function ($t) use ($handlers) {
            $fresh = $t->fresh();
            $this->assertCount(2, $fresh->handlers);
            $handlers->each(fn ($h) => $this->assertTrue(
                $fresh->subscribers()->whereKey($h->id)->exists()
            ));
        });
    }

    public function test_guest_cannot_bulk_update_status(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patch(route('tickets.bulk.status'), [
            'ticket_ids' => [$ticket->id],
            'status' => 'Cancelled',
        ])->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_bulk_update_status(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('tickets.bulk.status'), [
                'ticket_ids' => [$ticket->id],
                'status' => 'Cancelled',
            ])->assertForbidden();
    }

    // ── Bulk Handlers ────────────────────────────────────────────────────────

    public function test_admin_can_bulk_assign_handlers(): void
    {
        $admin = $this->admin();
        $tickets = Ticket::factory()->count(2)->create();
        $handlers = User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->patch(route('tickets.bulk.handlers'), [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
                'handler_ids' => $handlers->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        $tickets->each(fn ($t) => $this->assertCount(2, $t->fresh()->handlers));
    }

    public function test_bulk_handlers_replaces_existing_handlers(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $old = User::factory()->create();
        $ticket->handlers()->sync([$old->id]);

        $newHandler = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('tickets.bulk.handlers'), [
                'ticket_ids' => [$ticket->id],
                'handler_ids' => [$newHandler->id],
            ])
            ->assertRedirect();

        $this->assertCount(1, $ticket->fresh()->handlers);
        $this->assertTrue($ticket->fresh()->handlers->contains($newHandler));
        $this->assertFalse($ticket->fresh()->handlers->contains($old));
    }

    public function test_bulk_handlers_requires_handler_ids(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('tickets.bulk.handlers'), [
                'ticket_ids' => [$ticket->id],
                'handler_ids' => [],
            ])
            ->assertSessionHasErrors('handler_ids');
    }

    public function test_guest_cannot_bulk_assign_handlers(): void
    {
        $ticket = Ticket::factory()->create();
        $handler = User::factory()->create();

        $this->patch(route('tickets.bulk.handlers'), [
            'ticket_ids' => [$ticket->id],
            'handler_ids' => [$handler->id],
        ])->assertRedirect(route('login'));
    }

    // ── Bulk Delete ──────────────────────────────────────────────────────────

    public function test_admin_can_bulk_delete_tickets(): void
    {
        $admin = $this->admin();
        $tickets = Ticket::factory()->count(3)->create();

        $this->actingAs($admin)
            ->delete(route('tickets.bulk.destroy'), [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
            ])
            ->assertRedirect();

        $tickets->each(fn ($t) => $this->assertNull(Ticket::find($t->id)));
    }

    public function test_bulk_delete_requires_ticket_ids(): void
    {
        $this->actingAs($this->admin())
            ->delete(route('tickets.bulk.destroy'), ['ticket_ids' => []])
            ->assertSessionHasErrors('ticket_ids');
    }

    public function test_guest_cannot_bulk_delete(): void
    {
        $ticket = Ticket::factory()->create();

        $this->delete(route('tickets.bulk.destroy'), [
            'ticket_ids' => [$ticket->id],
        ])->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_bulk_delete(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('tickets.bulk.destroy'), [
                'ticket_ids' => [$ticket->id],
            ])->assertForbidden();
    }
}
