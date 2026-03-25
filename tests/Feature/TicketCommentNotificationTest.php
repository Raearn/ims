<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCommentPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketCommentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    // ── Subscribe / Unsubscribe ───────────────────────────────────────────────

    public function test_user_can_subscribe_to_a_ticket(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('tickets.subscribe.toggle', $ticket));

        $response->assertOk()->assertJson(['subscribed' => true]);

        $this->assertTrue(
            $ticket->subscribers()->where('user_id', $user->id)->exists()
        );
    }

    public function test_user_can_unsubscribe_from_a_ticket(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();
        $ticket->subscribers()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson(route('tickets.subscribe.toggle', $ticket));

        $response->assertOk()->assertJson(['subscribed' => false]);

        $this->assertFalse(
            $ticket->subscribers()->where('user_id', $user->id)->exists()
        );
    }

    public function test_comments_response_includes_subscribed_flag(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();
        $ticket->subscribers()->attach($user->id);

        $this->actingAs($user)
            ->getJson(route('tickets.comments.index', $ticket))
            ->assertOk()
            ->assertJsonPath('subscribed', true)
            ->assertJsonPath('comments', []);
    }

    // ── Auto-subscribe on comment ─────────────────────────────────────────────

    public function test_posting_a_comment_auto_subscribes_the_commenter(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Hello</p>']);

        $this->assertTrue(
            $ticket->subscribers()->where('user_id', $user->id)->exists()
        );
    }

    public function test_posting_again_does_not_duplicate_subscription(): void
    {
        $user = $this->user();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>First</p>']);

        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Second</p>']);

        $this->assertSame(
            1,
            $ticket->subscribers()->where('user_id', $user->id)->count()
        );
    }

    // ── Notification dispatch ─────────────────────────────────────────────────

    public function test_subscribers_receive_notification_when_comment_is_posted(): void
    {
        Notification::fake();

        $commenter = $this->user();
        $subscriber = $this->user();
        $ticket = Ticket::factory()->create();

        // Subscriber manually subscribed before the comment
        $ticket->subscribers()->attach($subscriber->id);

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Hey!</p>']);

        Notification::assertSentTo($subscriber, TicketCommentPosted::class, function ($n) use ($ticket, $subscriber) {
            return $n->ticket->id === $ticket->id
                && $n->toArray($subscriber)['type'] === 'ticket_comment_posted';
        });
    }

    public function test_commenter_does_not_receive_their_own_comment_notification(): void
    {
        Notification::fake();

        $commenter = $this->user();
        $ticket = Ticket::factory()->create();

        // Commenter is already subscribed
        $ticket->subscribers()->attach($commenter->id);

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Self post</p>']);

        Notification::assertNotSentTo($commenter, TicketCommentPosted::class);
    }

    public function test_no_notification_sent_when_no_other_subscribers(): void
    {
        Notification::fake();

        $commenter = $this->user();
        $ticket = Ticket::factory()->create();

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Solo</p>']);

        // Only the commenter is subscribed (auto-subscribed), nobody else
        Notification::assertNotSentTo($commenter, TicketCommentPosted::class);
    }

    public function test_multiple_subscribers_all_receive_notification(): void
    {
        Notification::fake();

        $commenter = $this->user();
        $subscriber1 = $this->user();
        $subscriber2 = $this->user();
        $ticket = Ticket::factory()->create();

        $ticket->subscribers()->attach([$subscriber1->id, $subscriber2->id]);

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>Group msg</p>']);

        Notification::assertSentTo($subscriber1, TicketCommentPosted::class);
        Notification::assertSentTo($subscriber2, TicketCommentPosted::class);
        Notification::assertNotSentTo($commenter, TicketCommentPosted::class);
    }

    public function test_notification_message_contains_ticket_id_and_commenter_name(): void
    {
        Notification::fake();

        $commenter = $this->user();
        $subscriber = $this->user();
        $ticket = Ticket::factory()->create(['title' => 'Printer broken']);
        $ticket->subscribers()->attach($subscriber->id);

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>On it</p>']);

        Notification::assertSentTo($subscriber, TicketCommentPosted::class, function ($n) use ($commenter, $ticket, $subscriber) {
            $data = $n->toArray($subscriber);

            return str_contains($data['message'], $commenter->name)
                && str_contains($data['message'], 'TKT-'.(1000 + $ticket->id))
                && $data['ticket_id'] === $ticket->id;
        });
    }

    // ── Notification stored in database ──────────────────────────────────────

    public function test_notification_is_stored_in_database(): void
    {
        $commenter = $this->user();
        $subscriber = $this->user();
        $ticket = Ticket::factory()->create();
        $ticket->subscribers()->attach($subscriber->id);

        $this->actingAs($commenter)
            ->postJson(route('tickets.comments.store', $ticket), ['body' => '<p>DB test</p>']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $subscriber->id,
            'notifiable_type' => User::class,
        ]);

        $this->assertSame(1, $subscriber->unreadNotifications()->count());
    }
}
