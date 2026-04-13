<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketActivityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Ticket created ────────────────────────────────────────────────────────

    public function test_creating_a_ticket_logs_created_activity(): void
    {
        $admin = $this->admin();

        $softwareId = TicketCategory::query()->where('name', 'Software')->value('id');
        $this->assertNotNull($softwareId);

        $this->actingAs($admin)->post(route('tickets.store'), [
            'title' => 'Test ticket',
            'description' => null,
            'ticket_category_id' => $softwareId,
            'priority' => 'Medium',
            'status' => 'Open',
            'tags' => ['StoreTestTag'],
        ]);

        $ticket = Ticket::where('title', 'Test ticket')->firstOrFail();

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'created',
            'new_value' => 'Test ticket',
        ]);
    }

    // ── Status changed ────────────────────────────────────────────────────────

    public function test_status_change_logs_status_changed_activity(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'Open']);

        $this->actingAs($admin)
            ->patch(route('tickets.status.update', $ticket), ['status' => 'In Progress']);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
        ]);
    }

    // ── Priority changed ──────────────────────────────────────────────────────

    public function test_priority_change_logs_priority_changed_activity(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['priority' => 'Low', 'status' => 'Open']);
        $tag = Tag::query()->create(['name' => 'PriorityTestTag']);
        $ticket->tags()->sync([$tag->id]);

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority' => 'Critical',
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'Critical',
        ]);
    }

    // ── Handler assigned ──────────────────────────────────────────────────────

    public function test_assigning_handlers_logs_handler_assigned_activity(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create(['name' => 'Jane Handler']);
        $ticket = Ticket::factory()->create(['status' => 'Open']);

        $this->actingAs($admin)
            ->patch(route('tickets.handlers.update', $ticket), [
                'handler_ids' => [$handler->id],
                'status' => 'In Progress',
            ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'handler_assigned',
            'new_value' => 'Jane Handler',
        ]);
    }

    // ── Handler removed ───────────────────────────────────────────────────────

    public function test_removing_handlers_logs_handler_removed_activity(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create(['name' => 'Bob Removed']);
        $ticket = Ticket::factory()->create(['status' => 'Open']);
        $ticket->handlers()->attach($handler->id);
        $tag = Tag::query()->create(['name' => 'HandlerRemoveTag']);
        $ticket->tags()->sync([$tag->id]);

        // Use full-edit route: switching to Open clears handlers
        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority' => $ticket->priority,
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'handler_removed',
            'old_value' => 'Bob Removed',
        ]);
    }

    public function test_title_change_logs_ticket_edited_activity(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => 'Open', 'title' => 'Original']);
        $tag = Tag::query()->create(['name' => 'EditTitleTag']);
        $ticket->tags()->sync([$tag->id]);

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'title' => 'Updated title',
            'description' => $ticket->description,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority' => $ticket->priority,
            'status' => 'Open',
            'handler_ids' => [],
            'tags' => [$tag->name],
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_edited',
            'new_value' => 'Updated: Title',
        ]);
    }

    // ── Comment posted ────────────────────────────────────────────────────────

    public function test_posting_a_comment_logs_comment_posted_activity(): void
    {
        $user = $this->admin();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user)
            ->postJson(route('tickets.comments.store', $ticket), [
                'body' => '<p>Hello world</p>',
            ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'comment_posted',
            'new_value' => 'Hello world',
        ]);
    }

    // ── Comment deleted ───────────────────────────────────────────────────────

    public function test_deleting_a_comment_logs_comment_deleted_activity(): void
    {
        $user = $this->admin();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => '<p>Will be deleted</p>',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ticket-comments.destroy', $comment));

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'comment_deleted',
            'old_value' => 'Will be deleted',
        ]);
    }

    // ── Comment pinned ────────────────────────────────────────────────────────

    public function test_pinning_a_comment_logs_comment_pinned_activity(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['user_id' => $admin->id]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'is_pinned' => false,
            'body' => '<p>Pin me</p>',
        ]);

        $this->actingAs($admin)
            ->postJson(route('ticket-comments.pin', $comment));

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'comment_pinned',
            'new_value' => 'Pin me',
        ]);
    }

    // ── History API ───────────────────────────────────────────────────────────

    public function test_history_api_returns_correct_json_shape(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'Resolved',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('tickets.history', $ticket));

        $response->assertOk()
            ->assertJsonFragment([
                'action' => 'status_changed',
                'oldValue' => 'Open',
                'newValue' => 'Resolved',
                'userName' => $admin->name,
            ]);
    }

    public function test_history_api_is_auth_gated(): void
    {
        $ticket = Ticket::factory()->create();

        $this->getJson(route('tickets.history', $ticket))
            ->assertUnauthorized();
    }

    public function test_history_api_returns_entries_newest_first(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'priority_changed',
            'old_value' => 'Low',
            'new_value' => 'High',
            'created_at' => now()->subMinutes(30),
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'In Progress',
            'created_at' => now()->subMinutes(5),
        ]);

        $data = $this->actingAs($admin)
            ->getJson(route('tickets.history', $ticket))
            ->assertOk()
            ->json();

        $actions = array_column($data, 'action');
        $this->assertContains('status_changed', $actions);
        $this->assertContains('priority_changed', $actions);

        // status_changed (5 min ago) must appear before priority_changed (30 min ago)
        $statusIdx = array_search('status_changed', $actions);
        $priorityIdx = array_search('priority_changed', $actions);
        $this->assertLessThan($priorityIdx, $statusIdx);
    }

    public function test_history_api_forbidden_without_ticket_thread_access(): void
    {
        $user = User::factory()->create(['role' => 'technical']);
        $ticket = Ticket::factory()->create();

        $this->actingAs($user)
            ->getJson(route('tickets.history', $ticket))
            ->assertForbidden();
    }

    public function test_history_api_allowed_for_technical_when_reporter(): void
    {
        $user = User::factory()->create(['role' => 'technical']);
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'status_changed',
            'old_value' => 'Open',
            'new_value' => 'Resolved',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('tickets.history', $ticket))
            ->assertOk()
            ->assertJsonFragment(['action' => 'status_changed']);
    }
}
