<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiagnosticsBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_post_diagnostics_backup(): void
    {
        $this->post(route('diagnostics.backup'))
            ->assertRedirect(route('login'));
    }

    public function test_technical_user_cannot_post_diagnostics_backup(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->post(route('diagnostics.backup'))
            ->assertForbidden();
    }

    public function test_supervisor_is_redirected_from_diagnostics_backup(): void
    {
        $user = User::factory()->supervisor()->create();

        $this->actingAs($user)
            ->post(route('diagnostics.backup'))
            ->assertRedirect(route('supervisor.dashboard'));
    }

    public function test_admin_diagnostics_includes_db_and_backup_total_sizes(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Diagnostics MySQL size query is not run under the default test database driver.');
        }

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('diagnostics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Diagnostics')
                ->has('dbSizeMb')
                ->has('backupsTotalSizeMb'));
    }
}
