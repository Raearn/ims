<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_admins_can_visit_the_dashboard()
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('sparklineLabels')
                ->where('sparklineLabels', fn ($labels) => count($labels) === 11)
                ->has('stats.0.sparklineValueSuffix')
                ->has('priorityLegend')
                ->has('categoryLegend')
            );
    }

    public function test_supervisors_are_redirected_to_their_dashboard_when_accessing_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('supervisor.dashboard'));
    }

    public function test_supervisors_can_visit_supervisor_dashboard()
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($user);

        $response = $this->get(route('supervisor.dashboard'));
        $response->assertStatus(200);
    }

    public function test_technical_users_cannot_visit_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'technical']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        // CheckRole middleware currently redirects based on role
        // For technical, it hits the abort(403)
        $response->assertStatus(403);
    }

    public function test_dashboard_open_incidents_trend_shows_new_when_prior_window_has_no_matching_tickets(): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        Ticket::factory()->count(2)->create([
            'status' => 'Open',
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.0.trend', 'New')
                ->where('stats.0.showTrendArrow', true)
            );
    }

    public function test_dashboard_open_incidents_trend_percent_matches_prior_and_current_counts(): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        Ticket::factory()->create([
            'status' => 'Open',
            'created_at' => now()->subDays(10),
        ]);
        Ticket::factory()->count(3)->create([
            'status' => 'Open',
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.0.trend', '200%')
                ->where('stats.0.showTrendArrow', true)
            );
    }

    public function test_dashboard_top_recurring_lists_tags_by_ticket_volume_in_period(): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $tagHeavy = Tag::firstOrCreate(['name' => 'DashboardTagHeavy']);
        $tagLight = Tag::firstOrCreate(['name' => 'DashboardTagLight']);

        $t1 = Ticket::factory()->create(['created_at' => now()->subDay()]);
        $t2 = Ticket::factory()->create(['created_at' => now()->subDay()]);
        $t3 = Ticket::factory()->create(['created_at' => now()->subDay()]);
        $t1->tags()->attach($tagHeavy->id);
        $t2->tags()->attach($tagHeavy->id);
        $t3->tags()->attach($tagLight->id);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('topRecurring.0.tag', 'DashboardTagHeavy')
                ->where('topRecurring.0.count', 2)
                ->where('topRecurring.1.tag', 'DashboardTagLight')
                ->where('topRecurring.1.count', 1)
            );
    }

    public function test_dashboard_resolved_stat_widget_excludes_closed_tickets(): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        $resolvedAt = now()->subDay();

        Ticket::factory()->create([
            'status' => 'Resolved',
            'resolved_at' => $resolvedAt,
            'created_at' => $resolvedAt->copy()->subHours(2),
        ]);
        Ticket::factory()->create([
            'status' => 'Closed',
            'resolved_at' => $resolvedAt,
            'created_at' => $resolvedAt->copy()->subHours(2),
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.2.title', 'Resolved Incidents')
                ->where('stats.2.value', 1)
            );
    }

    public function test_guests_cannot_access_pdf_export(): void
    {
        $this->get(route('dashboard.export-pdf'))
            ->assertRedirect('/login');
    }

    #[DataProvider('pdfPeriodProvider')]
    public function test_admin_can_export_pdf_for_all_periods(string $period): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard.export-pdf', ['period' => $period]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public static function pdfPeriodProvider(): array
    {
        return [
            ['7d'],
            ['30d'],
            ['this_month'],
            ['last_month'],
            ['ytd'],
            ['all'],
        ];
    }

    public function test_non_admin_cannot_access_pdf_export(): void
    {
        $user = User::factory()->create(['role' => 'technical']);
        $this->actingAs($user);

        $this->get(route('dashboard.export-pdf'))
            ->assertStatus(403);
    }

    public function test_pdf_export_defaults_to_7d_period_when_no_period_given(): void
    {
        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            $this->markTestSkipped('This test requires MySQL for TIMESTAMPDIFF function.');
        }

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard.export-pdf'));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));

        $this->assertDatabaseHas('ticket_activities', [
            'user_id' => $user->id,
            'ticket_id' => null,
            'action' => 'dashboard_export_pdf',
            'new_value' => 'Reporting period: 7d',
        ]);
        $this->assertSame(1, TicketActivity::query()->where('action', 'dashboard_export_pdf')->count());
    }
}
