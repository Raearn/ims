<?php

namespace Tests\Feature\Feature;

use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketConfigSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ── Categories ────────────────────────────────────────────────────────

    public function test_admin_can_update_categories(): void
    {
        TicketCategory::create(['name' => 'Old', 'icon' => 'Circle', 'sort_order' => 0]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', [
                'categories' => [
                    ['name' => 'Network', 'icon' => 'Network'],
                    ['name' => 'Hardware', 'icon' => 'HardDrive'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_categories', 2);
        $this->assertDatabaseHas('ticket_categories', ['name' => 'Network', 'icon' => 'Network', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_categories', ['name' => 'Hardware', 'icon' => 'HardDrive', 'sort_order' => 1]);
        $this->assertDatabaseMissing('ticket_categories', ['name' => 'Old']);
    }

    public function test_non_admin_cannot_update_categories(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->put('/admin/ticket-categories', ['categories' => [['name' => 'Hack', 'icon' => 'Circle']]])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_categories', ['name' => 'Hack']);
    }

    public function test_categories_update_requires_name_and_icon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', [
                'categories' => [['name' => '', 'icon' => 'Network']],
            ])
            ->assertSessionHasErrors('categories.0.name');
    }

    // ── Priorities ────────────────────────────────────────────────────────

    public function test_admin_can_update_priorities(): void
    {
        TicketPriority::create(['name' => 'OldPriority', 'icon' => 'Circle', 'color' => '#000', 'sort_order' => 0]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', [
                'priorities' => [
                    ['name' => 'Critical', 'icon' => 'AlertCircle', 'color' => '#f43f5e'],
                    ['name' => 'Low',      'icon' => 'Circle',       'color' => '#60a5fa'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_priorities', 2);
        $this->assertDatabaseHas('ticket_priorities', ['name' => 'Critical', 'color' => '#f43f5e', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_priorities', ['name' => 'Low', 'sort_order' => 1]);
        $this->assertDatabaseMissing('ticket_priorities', ['name' => 'OldPriority']);
    }

    public function test_non_admin_cannot_update_priorities(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->put('/admin/ticket-priorities', [
                'priorities' => [['name' => 'Hack', 'icon' => 'Circle', 'color' => '#000']],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_priorities', ['name' => 'Hack']);
    }

    // ── Statuses ──────────────────────────────────────────────────────────

    public function test_admin_can_update_statuses(): void
    {
        TicketStatus::create(['name' => 'OldStatus', 'sort_order' => 0]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', [
                'statuses' => [
                    ['name' => 'Open'],
                    ['name' => 'Resolved'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_statuses', 2);
        $this->assertDatabaseHas('ticket_statuses', ['name' => 'Open', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_statuses', ['name' => 'Resolved', 'sort_order' => 1]);
        $this->assertDatabaseMissing('ticket_statuses', ['name' => 'OldStatus']);
    }

    public function test_non_admin_cannot_update_statuses(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->put('/admin/ticket-statuses', ['statuses' => [['name' => 'Hack']]])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_statuses', ['name' => 'Hack']);
    }

    public function test_statuses_update_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', [
                'statuses' => [['name' => '']],
            ])
            ->assertSessionHasErrors('statuses.0.name');
    }
}
