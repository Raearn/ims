<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCommentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_comments_for_a_ticket(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);
        TicketComment::factory()->count(3)->create(['ticket_id' => $ticket->id]);

        $response = $this->actingAs($user)
            ->getJson(route('tickets.comments.index', $ticket));

        $response->assertOk()->assertJsonCount(3, 'comments');
    }

    public function test_unauthenticated_user_cannot_list_comments(): void
    {
        $ticket = Ticket::factory()->create();

        $this->getJson(route('tickets.comments.index', $ticket))
            ->assertUnauthorized();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_post_a_comment(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), [
                'body' => '<p>Hello world</p>',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['body' => '<p>Hello world</p>']);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => '<p>Hello world</p>',
        ]);
    }

    public function test_comment_body_is_required(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_user_cannot_list_comments_on_ticket_without_thread_access(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user)
            ->getJson(route('tickets.comments.index', $ticket))
            ->assertForbidden();
    }

    public function test_user_can_list_comments_when_assigned_handler(): void
    {
        $user = $this->user();
        $other = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $other->id]);
        $ticket->handlers()->attach($user->id);
        TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($user)
            ->getJson(route('tickets.comments.index', $ticket))
            ->assertOk()
            ->assertJsonCount(1, 'comments');
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_user_can_delete_their_own_comment(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ticket-comments.destroy', $comment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->deleteJson(route('ticket-comments.destroy', $comment))
            ->assertForbidden();

        $this->assertDatabaseHas('ticket_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('ticket-comments.destroy', $comment))
            ->assertOk();

        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
    }

    public function test_supervisor_can_delete_another_users_comment(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $owner = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($supervisor)
            ->deleteJson(route('ticket-comments.destroy', $comment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
    }

    public function test_supervisor_can_pin_comment_when_not_ticket_reporter(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $reporter = $this->user();
        $ticket = Ticket::factory()->create(['user_id' => $reporter->id]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'is_pinned' => false,
        ]);

        $this->actingAs($supervisor)
            ->postJson(route('ticket-comments.pin', $comment))
            ->assertOk()
            ->assertJson(['isPinned' => true]);

        $this->assertSame(1, (int) $comment->fresh()->is_pinned);
    }

    public function test_unauthenticated_user_cannot_delete_a_comment(): void
    {
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $this->deleteJson(route('ticket-comments.destroy', $comment))
            ->assertUnauthorized();
    }
}
