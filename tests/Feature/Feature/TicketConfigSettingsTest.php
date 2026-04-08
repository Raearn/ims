<?php

namespace Tests\Feature\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\TicketConfigDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketConfigSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ── Categories ────────────────────────────────────────────────────────

    public function test_admin_can_update_categories(): void
    {
        TicketCategory::create(['name' => 'Old', 'icon' => 'Circle', 'sort_order' => 99]);
        $admin = User::factory()->admin()->create();

        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon']],
            TicketConfigDefaults::categories(),
        );
        $othersIdx = array_search('Others', array_column($rows, 'name'), true);
        $this->assertNotFalse($othersIdx);
        $rows[$othersIdx]['icon'] = 'Globe';

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', ['categories' => $rows])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_categories', 6);
        $this->assertDatabaseHas('ticket_categories', ['name' => 'Network', 'icon' => 'Network', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_categories', ['name' => 'Others', 'icon' => 'Globe']);
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
        TicketPriority::create(['name' => 'OldPriority', 'icon' => 'Circle', 'color' => '#000', 'sort_order' => 99]);
        $admin = User::factory()->admin()->create();

        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']],
            TicketConfigDefaults::priorities(),
        );
        $rows[] = ['name' => 'CustomPrio', 'icon' => 'Star', 'color' => '#aabbcc'];

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', ['priorities' => $rows])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_priorities', 5);
        $this->assertDatabaseHas('ticket_priorities', ['name' => 'Critical', 'color' => '#f43f5e', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_priorities', ['name' => 'CustomPrio', 'color' => '#aabbcc', 'sort_order' => 4]);
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
        TicketStatus::create([
            'name' => 'OldStatus',
            'icon' => 'Circle',
            'color' => '#000000',
            'handler_requirement' => 'optional',
            'sort_order' => 99,
        ]);
        $admin = User::factory()->admin()->create();

        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            TicketConfigDefaults::statuses(),
        );
        $rows[] = [
            'name' => 'CustomStatus',
            'icon' => 'Flag',
            'color' => '#9333ea',
            'handler_requirement' => 'optional',
        ];

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_statuses', 6);
        $this->assertDatabaseHas('ticket_statuses', ['name' => 'Open', 'icon' => 'AlertTriangle', 'color' => '#f43f5e', 'handler_requirement' => 'none', 'sort_order' => 0]);
        $this->assertDatabaseHas('ticket_statuses', ['name' => 'CustomStatus', 'color' => '#9333ea', 'sort_order' => 5]);
        $this->assertDatabaseMissing('ticket_statuses', ['name' => 'OldStatus']);
    }

    public function test_non_admin_cannot_update_statuses(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->put('/admin/ticket-statuses', ['statuses' => [['name' => 'Hack', 'icon' => 'Circle', 'color' => '#000', 'handler_requirement' => 'optional']]])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_statuses', ['name' => 'Hack']);
    }

    public function test_statuses_update_requires_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', [
                'statuses' => [['name' => '', 'icon' => 'Circle', 'color' => '#000', 'handler_requirement' => 'optional']],
            ])
            ->assertSessionHasErrors('statuses.0.name');
    }

    public function test_statuses_update_validates_handler_requirement(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', [
                'statuses' => [
                    ['name' => 'X', 'icon' => 'Circle', 'color' => '#000', 'handler_requirement' => 'invalid'],
                ],
            ])
            ->assertSessionHasErrors('statuses.0.handler_requirement');
    }

    public function test_cannot_omit_built_in_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon']],
            array_slice(TicketConfigDefaults::categories(), 0, 4),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', ['categories' => $rows])
            ->assertSessionHasErrors('categories');
    }

    public function test_cannot_change_built_in_category_icon(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon']],
            TicketConfigDefaults::categories(),
        );
        $rows[0]['icon'] = 'Globe';

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', ['categories' => $rows])
            ->assertSessionHasErrors('categories.0.icon');
    }

    public function test_cannot_change_built_in_priority_icon(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']],
            TicketConfigDefaults::priorities(),
        );
        $rows[0]['icon'] = 'Star';

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', ['priorities' => $rows])
            ->assertSessionHasErrors('priorities.0.icon');
    }

    public function test_cannot_change_built_in_priority_color(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']],
            TicketConfigDefaults::priorities(),
        );
        $rows[0]['color'] = '#ff0000';

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', ['priorities' => $rows])
            ->assertSessionHasErrors('priorities.0.color');
    }

    public function test_cannot_change_built_in_status_icon(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            TicketConfigDefaults::statuses(),
        );
        $rows[0]['icon'] = 'Circle';

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertSessionHasErrors('statuses.0.icon');
    }

    public function test_cannot_change_built_in_status_color(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            TicketConfigDefaults::statuses(),
        );
        $rows[0]['color'] = '#ff00ff';

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertSessionHasErrors('statuses.0.color');
    }

    public function test_admin_can_remove_others_category(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon']],
            array_values(array_filter(
                TicketConfigDefaults::categories(),
                fn (array $r) => $r['name'] !== 'Others',
            )),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', ['categories' => $rows])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('ticket_categories', 5);
        $this->assertDatabaseMissing('ticket_categories', ['name' => 'Others']);
    }

    public function test_cannot_omit_built_in_priorities(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']],
            array_slice(TicketConfigDefaults::priorities(), 0, 3),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', ['priorities' => $rows])
            ->assertSessionHasErrors('priorities');
    }

    public function test_cannot_omit_built_in_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            array_slice(TicketConfigDefaults::statuses(), 0, 4),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertSessionHasErrors('statuses');
    }

    public function test_cannot_change_built_in_status_handler_requirement(): void
    {
        $admin = User::factory()->admin()->create();
        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            TicketConfigDefaults::statuses(),
        );

        foreach ($rows as $index => $row) {
            if ($row['name'] === 'Open') {
                $rows[$index]['handler_requirement'] = 'required';
                break;
            }
        }

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertSessionHasErrors('statuses.'.$index.'.handler_requirement');

        $this->assertDatabaseHas('ticket_statuses', ['name' => 'Open', 'handler_requirement' => 'none']);
    }

    public function test_cannot_remove_ticket_status_while_tickets_use_it(): void
    {
        $admin = User::factory()->admin()->create();
        TicketStatus::create([
            'name' => 'Escalated',
            'icon' => 'Flag',
            'color' => '#9333ea',
            'handler_requirement' => 'optional',
            'sort_order' => 100,
        ]);
        Ticket::factory()->create([
            'status' => 'Escalated',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $rows = array_map(
            fn (array $r) => [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'color' => $r['color'],
                'handler_requirement' => $r['handler_requirement'],
            ],
            TicketConfigDefaults::statuses(),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-statuses', ['statuses' => $rows])
            ->assertSessionHasErrors('statuses');

        $this->assertDatabaseHas('ticket_statuses', ['name' => 'Escalated']);
        $this->assertDatabaseCount('ticket_statuses', 6);
    }

    public function test_admin_can_delete_all_tickets_for_a_status(): void
    {
        $admin = User::factory()->admin()->create();
        $onHold = TicketStatus::query()->where('name', 'On Hold')->firstOrFail();
        Ticket::factory()->count(2)->create([
            'status' => 'On Hold',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.ticket-statuses.tickets.destroy', $onHold))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['status' => 'On Hold']);
        $this->assertDatabaseHas('ticket_statuses', ['id' => $onHold->id, 'name' => 'On Hold']);
    }

    public function test_non_admin_cannot_delete_tickets_for_a_status(): void
    {
        $onHold = TicketStatus::query()->where('name', 'On Hold')->firstOrFail();
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->delete(route('admin.ticket-statuses.tickets.destroy', $onHold))
            ->assertForbidden();
    }

    public function test_delete_tickets_for_status_reports_success_when_no_tickets_match(): void
    {
        $admin = User::factory()->admin()->create();
        $onHold = TicketStatus::query()->where('name', 'On Hold')->firstOrFail();
        Ticket::query()->where('status', 'On Hold')->delete();

        $this->actingAs($admin)
            ->delete(route('admin.ticket-statuses.tickets.destroy', $onHold))
            ->assertRedirect()
            ->assertSessionHas('success', 'No tickets used that status.');
    }

    public function test_cannot_remove_category_while_tickets_use_it(): void
    {
        $admin = User::factory()->admin()->create();
        TicketCategory::create(['name' => 'TempCat', 'icon' => 'Tag', 'sort_order' => 100]);
        Ticket::factory()->create([
            'category' => 'TempCat',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon']],
            TicketConfigDefaults::categories(),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-categories', ['categories' => $rows])
            ->assertSessionHasErrors('categories');

        $this->assertDatabaseHas('ticket_categories', ['name' => 'TempCat']);
    }

    public function test_admin_can_delete_all_tickets_for_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $network = TicketCategory::query()->where('name', 'Network')->firstOrFail();
        Ticket::factory()->count(2)->create([
            'category' => 'Network',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.ticket-categories.tickets.destroy', $network))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['category' => 'Network']);
        $this->assertDatabaseHas('ticket_categories', ['id' => $network->id, 'name' => 'Network']);
    }

    public function test_non_admin_cannot_delete_tickets_for_a_category(): void
    {
        $network = TicketCategory::query()->where('name', 'Network')->firstOrFail();
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->delete(route('admin.ticket-categories.tickets.destroy', $network))
            ->assertForbidden();
    }

    public function test_cannot_remove_priority_while_tickets_use_it(): void
    {
        $admin = User::factory()->admin()->create();
        TicketPriority::create(['name' => 'TempPrio', 'icon' => 'Star', 'color' => '#aabbcc', 'sort_order' => 100]);
        Ticket::factory()->create([
            'priority' => 'TempPrio',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $rows = array_map(
            fn (array $r) => ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']],
            TicketConfigDefaults::priorities(),
        );

        $this->actingAs($admin)
            ->put('/admin/ticket-priorities', ['priorities' => $rows])
            ->assertSessionHasErrors('priorities');

        $this->assertDatabaseHas('ticket_priorities', ['name' => 'TempPrio']);
    }

    public function test_admin_can_delete_all_tickets_for_a_priority(): void
    {
        $admin = User::factory()->admin()->create();
        $high = TicketPriority::query()->where('name', 'High')->firstOrFail();
        Ticket::factory()->count(2)->create([
            'priority' => 'High',
            'user_id' => $admin->id,
            'assigned_to' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.ticket-priorities.tickets.destroy', $high))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['priority' => 'High']);
        $this->assertDatabaseHas('ticket_priorities', ['id' => $high->id, 'name' => 'High']);
    }

    public function test_non_admin_cannot_delete_tickets_for_a_priority(): void
    {
        $high = TicketPriority::query()->where('name', 'High')->firstOrFail();
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->delete(route('admin.ticket-priorities.tickets.destroy', $high))
            ->assertForbidden();
    }
}
