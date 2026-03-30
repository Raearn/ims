<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function technician(): User
    {
        return User::factory()->create(['role' => 'technical']);
    }

    // ── TicketAssigned ───────────────────────────────────────────────────────

    public function test_ticket_assigned_notification_sent_when_handlers_assigned_via_create(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $handler = $this->technician();

        $this->actingAs($admin)
            ->post(route('tickets.store'), [
                'title' => 'Test Ticket',
                'category' => 'Network',
                'priority' => 'High',
                'status' => 'In Progress',
                'handler_ids' => [$handler->id],
                'tags' => ['NotifyTag'],
            ]);

        Notification::assertSentTo($handler, TicketAssigned::class);
    }

    public function test_ticket_assigned_notification_not_sent_when_no_handlers(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('tickets.store'), [
                'title' => 'Unassigned Ticket',
                'category' => 'Network',
                'priority' => 'Low',
                'status' => 'Open',
                'tags' => ['NotifyTag'],
            ]);

        Notification::assertNothingSent();
    }

    public function test_ticket_assigned_notification_sent_on_handler_update(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $handler = $this->technician();
        $ticket = Ticket::factory()->create(['status' => 'Open']);

        $this->actingAs($admin)
            ->patch(route('tickets.handlers.update', $ticket), [
                'handler_ids' => [$handler->id],
                'status' => 'In Progress',
            ]);

        Notification::assertSentTo($handler, TicketAssigned::class);
    }

    public function test_ticket_assigned_notification_not_sent_to_existing_handlers(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $existingHandler = $this->technician();
        $newHandler = $this->technician();
        $ticket = Ticket::factory()->create(['status' => 'In Progress']);
        $ticket->handlers()->attach($existingHandler->id);

        $this->actingAs($admin)
            ->patch(route('tickets.handlers.update', $ticket), [
                'handler_ids' => [$existingHandler->id, $newHandler->id],
            ]);

        Notification::assertSentTo($newHandler, TicketAssigned::class);
        Notification::assertNotSentTo($existingHandler, TicketAssigned::class);
    }

    // ── TicketStatusChanged ──────────────────────────────────────────────────

    public function test_reporter_notified_when_status_changes_via_status_update(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $reporter = User::factory()->create();
        $handler = $this->technician();
        $ticket = Ticket::factory()->create([
            'status' => 'Open',
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => 'In Progress',
                'handler_ids' => [$handler->id],
            ]);

        Notification::assertSentTo($reporter, TicketStatusChanged::class, function ($notification) {
            return $notification->oldStatus === 'Open' && $notification->newStatus === 'In Progress';
        });
    }

    public function test_reporter_not_notified_when_status_unchanged(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $reporter = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'In Progress',
            'user_id' => $reporter->id,
        ]);
        $handler = $this->technician();
        $ticket->handlers()->attach($handler->id);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => 'In Progress',
            ]);

        Notification::assertNotSentTo($reporter, TicketStatusChanged::class);
    }

    // ── Notification API routes ──────────────────────────────────────────────

    public function test_authenticated_user_can_fetch_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new TicketAssigned(Ticket::factory()->create()));

        $this->actingAs($user)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.data.type', 'ticket_assigned');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();
        $user->notify(new TicketAssigned($ticket));
        $notification = $user->notifications()->first();

        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->postJson("/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();
        $user->notify(new TicketAssigned($ticket));
        $user->notify(new TicketAssigned($ticket));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->postJson('/notifications/read-all')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/notifications')->assertUnauthorized();
    }

    // ── Shared unread count ──────────────────────────────────────────────────

    public function test_unread_notifications_count_increments_and_decrements_correctly(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->assertEquals(0, $user->unreadNotifications()->count());

        $user->notify(new TicketAssigned($ticket));
        $this->assertEquals(1, $user->unreadNotifications()->count());

        $notification = $user->notifications()->first();
        $notification->markAsRead();
        $this->assertEquals(0, $user->unreadNotifications()->count());
    }
}
