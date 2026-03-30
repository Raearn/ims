<?php

namespace Tests\Feature\Feature;

use App\Models\Setting;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ── Authorization ─────────────────────────────────────────────────────

    public function test_guests_cannot_access_settings_page(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_settings_page(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        // Technical users get a 403 (no specific redirect for them)
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings'));
    }

    // ── Index data ────────────────────────────────────────────────────────

    public function test_settings_are_grouped_and_passed_to_the_view(): void
    {
        Setting::create(['key' => 'app_name', 'value' => 'Test App', 'type' => 'string', 'group' => 'general']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->has('settings.general.app_name')
            );
    }

    public function test_settings_page_includes_ticket_config_props(): void
    {
        TicketCategory::create(['name' => 'Network', 'icon' => 'Network', 'sort_order' => 0]);
        TicketPriority::create(['name' => 'High', 'icon' => 'AlertTriangle', 'color' => '#f97316', 'sort_order' => 0]);
        TicketStatus::create(['name' => 'Open', 'sort_order' => 0]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->has('categories')
                ->has('priorities')
                ->has('statuses')
                ->has('ticketConfigProtectedNames')
                ->has('categoryTicketCountsById')
                ->has('priorityTicketCountsById')
                ->has('statusTicketCountsById')
            );
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function test_non_admin_cannot_update_settings(): void
    {
        $user = User::factory()->create(['role' => 'supervisor']);

        // CheckRole redirects supervisors rather than returning 403
        $this->actingAs($user)
            ->put('/admin/settings', ['settings' => ['app_name' => 'Hacked']])
            ->assertRedirect();

        // Confirm the setting was not changed
        $this->assertDatabaseMissing('settings', ['key' => 'app_name', 'value' => 'Hacked']);
    }

    public function test_admin_can_update_string_setting(): void
    {
        Setting::create(['key' => 'app_name', 'value' => 'Old Name', 'type' => 'string', 'group' => 'general']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => ['app_name' => 'New Name']])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'New Name']);
    }

    public function test_admin_can_update_boolean_setting(): void
    {
        Setting::create(['key' => 'fixture_boolean_setting', 'value' => '0', 'type' => 'boolean', 'group' => 'general']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => ['fixture_boolean_setting' => true]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'fixture_boolean_setting', 'value' => '1']);
    }

    public function test_update_ignores_unknown_keys(): void
    {
        $admin = User::factory()->admin()->create();

        // Key doesn't exist in settings table — should not throw
        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => ['non_existent_key' => 'value']])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_update_requires_settings_array(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', [])
            ->assertSessionHasErrors('settings');
    }
}
