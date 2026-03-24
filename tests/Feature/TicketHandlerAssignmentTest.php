<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketHandlerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_assign_handlers_to_ticket(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $handlers = User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => $handlers->pluck('id')->toArray()]
        );

        $response->assertRedirect();
        $this->assertCount(2, $ticket->fresh()->handlers);
        $handlers->each(fn ($h) => $this->assertTrue($ticket->fresh()->handlers->contains($h)));
    }

    public function test_assigning_handlers_replaces_existing_ones(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $oldHandler = User::factory()->create();
        $ticket->handlers()->sync([$oldHandler->id]);

        $newHandler = User::factory()->create();

        $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [$newHandler->id]]
        );

        $this->assertCount(1, $ticket->fresh()->handlers);
        $this->assertTrue($ticket->fresh()->handlers->contains($newHandler));
        $this->assertFalse($ticket->fresh()->handlers->contains($oldHandler));
    }

    public function test_handler_ids_is_required(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);

        $response = $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => []]
        );

        $response->assertSessionHasErrors('handler_ids');
    }

    public function test_handler_ids_must_reference_existing_users(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);

        $response = $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [99999]]
        );

        $response->assertSessionHasErrors('handler_ids.0');
    }

    public function test_guest_cannot_assign_handlers(): void
    {
        $ticket = Ticket::factory()->create();
        $handler = User::factory()->create();

        $response = $this->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [$handler->id]]
        );

        $response->assertRedirect(route('login'));
    }

    public function test_cannot_assign_handlers_to_open_ticket_without_status(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'Open']);
        $handler = User::factory()->create();

        $response = $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [$handler->id]]
        );

        $response->assertStatus(422);
        $this->assertCount(0, $ticket->fresh()->handlers);
    }

    public function test_can_assign_handlers_to_open_ticket_with_status(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'Open']);
        $handler = User::factory()->create();

        $response = $this->actingAs($admin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [$handler->id], 'status' => 'In Progress']
        );

        $response->assertRedirect();
        $this->assertCount(1, $ticket->fresh()->handlers);
        $this->assertSame('In Progress', $ticket->fresh()->status);
    }

    public function test_can_assign_handlers_to_non_open_statuses(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create();

        foreach (['In Progress', 'On Hold', 'Resolved'] as $status) {
            $ticket = Ticket::factory()->create(['status' => $status]);

            $this->actingAs($admin)->patch(
                route('tickets.handlers.update', $ticket),
                ['handler_ids' => [$handler->id]]
            )->assertRedirect();

            $this->assertCount(1, $ticket->fresh()->handlers);
        }
    }

    public function test_non_admin_cannot_assign_handlers(): void
    {
        $nonAdmin = User::factory()->create(); // default role, not admin
        $ticket = Ticket::factory()->create();
        $handler = User::factory()->create();

        $response = $this->actingAs($nonAdmin)->patch(
            route('tickets.handlers.update', $ticket),
            ['handler_ids' => [$handler->id]]
        );

        $response->assertForbidden();
    }
}
