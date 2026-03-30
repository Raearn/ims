<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsByTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_tickets_by_tag(): void
    {
        $this->getJson(route('tickets.by-tag', ['name' => 'Alpha']))
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_tickets_by_tag(): void
    {
        $user = User::factory()->create(['role' => 'technical']);

        $this->actingAs($user)
            ->getJson(route('tickets.by-tag', ['name' => 'Alpha']))
            ->assertForbidden();
    }

    public function test_admin_gets_validation_error_when_name_missing(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('tickets.by-tag', []))
            ->assertUnprocessable();
    }

    public function test_admin_gets_empty_json_when_tag_does_not_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('tickets.by-tag', ['name' => 'NonexistentTag']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_admin_can_list_tickets_for_tag_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::query()->create(['name' => 'RecurringTheme']);
        $ticket = Ticket::factory()->create();
        $ticket->tags()->attach($tag);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-tag', ['name' => 'RecurringTheme']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tktId', 'TKT-'.(1000 + $ticket->id))
            ->assertJsonPath('0.title', $ticket->title);
    }

    public function test_admin_only_sees_tickets_with_matching_tag(): void
    {
        $admin = User::factory()->admin()->create();
        $tagA = Tag::query()->create(['name' => 'ThemeA']);
        $tagB = Tag::query()->create(['name' => 'ThemeB']);
        $withA = Ticket::factory()->create();
        $withA->tags()->attach($tagA);
        $withB = Ticket::factory()->create();
        $withB->tags()->attach($tagB);

        $this->actingAs($admin)
            ->getJson(route('tickets.by-tag', ['name' => 'ThemeA']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.numericId', $withA->id);
    }
}
