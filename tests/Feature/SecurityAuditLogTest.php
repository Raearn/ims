<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_is_logged_without_password(): void
    {
        $this->post('/login', [
            'email' => 'nope@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('ticket_activities', [
            'action' => 'user_login_failed',
            'user_id' => null,
        ]);

        $row = TicketActivity::query()->where('action', 'user_login_failed')->firstOrFail();
        $this->assertStringContainsString('nope@example.com', (string) $row->new_value);
        $this->assertStringNotContainsString('wrong-password', (string) $row->new_value);
    }

    public function test_login_lockout_is_logged_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'lock@example.com',
                'password' => 'bad',
            ]);
        }

        $this->post('/login', [
            'email' => 'lock@example.com',
            'password' => 'bad',
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('ticket_activities', [
            'action' => 'user_login_lockout',
        ]);
    }

    public function test_admin_role_change_writes_user_role_changed_row(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'technical', 'email' => 't@example.com']);

        $this->actingAs($admin)->patch(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'supervisor',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_activities', [
            'user_id' => $admin->id,
            'action' => 'user_role_changed',
            'old_value' => $target->name.' ('.$target->email.') — technical',
            'new_value' => $target->name.' ('.$target->email.') — supervisor',
        ]);

        $this->assertDatabaseMissing('ticket_activities', [
            'user_id' => $admin->id,
            'action' => 'password_changed_by_admin',
        ]);
    }

    public function test_admin_setting_user_password_writes_password_changed_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'technical']);

        $this->actingAs($admin)->patch(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_activities', [
            'action' => 'password_changed_by_admin',
            'user_id' => $admin->id,
        ]);
    }

    public function test_settings_update_logs_when_value_changes(): void
    {
        Setting::create([
            'key' => 'app_name',
            'value' => 'Before',
            'type' => 'string',
            'group' => 'general',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => ['app_name' => 'After']])
            ->assertRedirect();

        $row = TicketActivity::query()
            ->where('user_id', $admin->id)
            ->where('action', 'settings_updated')
            ->firstOrFail();

        $this->assertStringContainsString('app_name: Before', (string) $row->old_value);
        $this->assertStringContainsString('app_name: After', (string) $row->new_value);
    }

    public function test_ticket_put_with_new_file_logs_attachment_uploaded(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'Open',
            'category' => 'Software',
            'priority' => 'Medium',
        ]);

        $tag = Tag::create(['name' => 'SecTag']);

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'handler_ids' => [],
            'tags' => [$tag->name],
            'attachment' => UploadedFile::fake()->image('x.jpg', 100, 100),
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_edited',
            'new_value' => 'Updated: Attachment',
        ]);
    }

    public function test_ticket_put_tag_change_logs_ticket_tags_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'Open',
            'category' => 'Software',
            'priority' => 'Medium',
        ]);
        $ticket->tags()->sync([Tag::create(['name' => 'Alpha'])->id]);

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'handler_ids' => [],
            'tags' => ['Beta'],
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_edited',
            'old_value' => 'Alpha',
            'new_value' => 'Beta',
        ]);
    }
}
