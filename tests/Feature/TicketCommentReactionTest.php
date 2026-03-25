<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCommentReactionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    // ── Toggle ───────────────────────────────────────────────────────────────

    public function test_user_can_add_a_reaction_to_a_comment(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $response = $this->actingAs($user)
            ->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '👍']);

        $response->assertOk()
            ->assertJson(['emoji' => '👍', 'count' => 1, 'reacted' => true]);

        $this->assertDatabaseHas('ticket_comment_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_toggling_an_existing_reaction_removes_it(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        TicketCommentReaction::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'emoji' => '❤️',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '❤️']);

        $response->assertOk()
            ->assertJson(['emoji' => '❤️', 'count' => 0, 'reacted' => false]);

        $this->assertDatabaseMissing('ticket_comment_reactions', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'emoji' => '❤️',
        ]);
    }

    public function test_multiple_users_can_react_with_the_same_emoji(): void
    {
        $user1 = $this->user();
        $user2 = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($user1)
            ->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '🎉']);

        $response = $this->actingAs($user2)
            ->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '🎉']);

        $response->assertOk()
            ->assertJson(['emoji' => '🎉', 'count' => 2, 'reacted' => true]);
    }

    public function test_disallowed_emoji_is_rejected(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $this->actingAs($user)
            ->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '🦄'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emoji']);
    }

    public function test_unauthenticated_user_cannot_react(): void
    {
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);

        $this->postJson(route('ticket-comments.reactions.toggle', $comment), ['emoji' => '👍'])
            ->assertUnauthorized();
    }
}
