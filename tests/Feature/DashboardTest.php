<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admins_can_visit_the_dashboard()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_supervisors_are_redirected_to_their_dashboard_when_accessing_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($user);

        $response = $this->get('/dashboard');
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

        $response = $this->get('/dashboard');
        // CheckRole middleware currently redirects based on role
        // For technical, it hits the abort(403)
        $response->assertStatus(403);
    }
}
