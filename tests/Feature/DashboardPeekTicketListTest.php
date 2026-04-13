<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPeekTicketListTest extends TestCase
{
    use RefreshDatabase;

    public function test_by_priority_respects_dashboard_period(): void
    {
        $admin = User::factory()->admin()->create();

        Ticket::factory()->create([
            'priority' => 'Critical',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(20),
        ]);
        Ticket::factory()->create([
            'priority' => 'Critical',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-priority', ['priority' => 'Critical']).'?period=7d')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-priority', ['priority' => 'Critical']).'?period=30d')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_by_category_respects_dashboard_period(): void
    {
        $admin = User::factory()->admin()->create();

        Ticket::factory()->create([
            'category' => 'Network',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(20),
        ]);
        Ticket::factory()->create([
            'category' => 'Network',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-category', ['category' => 'Network']).'?period=7d')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_by_category_root_returns_tickets_for_root_and_direct_children(): void
    {
        $admin = User::factory()->admin()->create();

        $network = TicketCategory::query()->create([
            'name' => 'NetworkRootPeek',
            'icon' => 'Network',
            'sort_order' => 0,
            'parent_id' => null,
        ]);
        $child = TicketCategory::query()->create([
            'name' => 'ISPChildPeek',
            'icon' => 'Network',
            'sort_order' => 0,
            'parent_id' => $network->id,
        ]);

        Ticket::factory()->create([
            'category' => 'ISPChildPeek',
            'ticket_category_id' => $child->id,
            'user_id' => $admin->id,
            'created_at' => now()->subDays(1),
        ]);
        Ticket::factory()->create([
            'category' => 'NetworkRootPeek',
            'ticket_category_id' => $network->id,
            'user_id' => $admin->id,
            'created_at' => now()->subDays(1),
        ]);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-category-root', $network).'?period=7d')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_by_category_root_returns_404_for_non_root_category(): void
    {
        $admin = User::factory()->admin()->create();

        $network = TicketCategory::query()->create([
            'name' => 'RootFor404',
            'icon' => 'Network',
            'sort_order' => 0,
            'parent_id' => null,
        ]);
        $child = TicketCategory::query()->create([
            'name' => 'ChildFor404',
            'icon' => 'Network',
            'sort_order' => 0,
            'parent_id' => $network->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-category-root', $child).'?period=7d')
            ->assertNotFound();
    }

    public function test_by_open_respects_dashboard_period_and_status(): void
    {
        $admin = User::factory()->admin()->create();

        Ticket::factory()->create([
            'status' => 'Open',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(20),
        ]);
        Ticket::factory()->create([
            'status' => 'Open',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);
        Ticket::factory()->create([
            'status' => 'Resolved',
            'user_id' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-open').'?period=7d')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-open').'?period=30d')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_by_tag_respects_dashboard_period(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::query()->create(['name' => 'DashboardPeekTag']);

        $old = Ticket::factory()->create([
            'user_id' => $admin->id,
            'created_at' => now()->subDays(20),
        ]);
        $old->tags()->attach($tag->id);

        $recent = Ticket::factory()->create([
            'user_id' => $admin->id,
            'created_at' => now()->subDays(2),
        ]);
        $recent->tags()->attach($tag->id);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-tag').'?'.http_build_query(['name' => 'DashboardPeekTag', 'period' => '7d']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_peek_routes_validate_period(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('tickets.by-priority', ['priority' => 'Critical']).'?period=invalid')
            ->assertUnprocessable();
    }

    public function test_peek_routes_require_admin(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->getJson(route('tickets.by-priority', ['priority' => 'Critical']).'?period=7d')
            ->assertForbidden();
    }
}
