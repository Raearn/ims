<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSolutionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_solutions_page(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::firstOrCreate(['name' => 'Database']);
        $ticket = Ticket::factory()->create([
            'title' => 'Cannot connect to database',
            'solution' => 'Restarted the database service',
            'status' => 'Resolved',
        ]);
        $ticket->tags()->attach($tag->id);

        $this->actingAs($admin)
            ->get(route('admin.solutions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Solutions')
                ->has('tags', 1)
                ->where('tags.0.name', 'Database')
                ->where('tags.0.solutions.0.ticket_title', 'Cannot connect to database')
                ->where('tags.0.solutions.0.solution', 'Restarted the database service')
                ->has('tags.0.solutions.0.tags', 1)
                ->where('tags.0.solutions.0.tags.0', 'Database')
            );
    }

    public function test_supervisor_cannot_access_solutions_page(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)
            ->get(route('admin.solutions'))
            ->assertRedirect(route('supervisor.dashboard'));
    }

    public function test_technical_cannot_access_solutions_page(): void
    {
        $technical = User::factory()->create(['role' => 'technical']);

        $this->actingAs($technical)
            ->get(route('admin.solutions'))
            ->assertRedirect(route('home'));
    }

    public function test_solutions_only_includes_tickets_with_solutions(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::firstOrCreate(['name' => 'Network']);
        
        // Ticket with solution
        $ticketWithSolution = Ticket::factory()->create([
            'title' => 'Network down',
            'solution' => 'Fixed the router',
            'status' => 'Resolved',
        ]);
        $ticketWithSolution->tags()->attach($tag->id);

        // Ticket without solution
        $ticketWithoutSolution = Ticket::factory()->create([
            'title' => 'Slow network',
            'solution' => null,
            'status' => 'In Progress',
        ]);
        $ticketWithoutSolution->tags()->attach($tag->id);

        $this->actingAs($admin)
            ->get(route('admin.solutions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tags', 1)
                ->where('tags.0.name', 'Network')
                ->has('tags.0.solutions', 1)
                ->where('tags.0.solutions.0.ticket_title', 'Network down')
            );
    }
}