<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['Open', 'In Progress', 'On Hold', 'Resolved', 'Cancelled']),
            'priority' => fake()->randomElement(['Low', 'Medium', 'High', 'Critical']),
            'category' => fake()->randomElement(['Network', 'Hardware', 'Software', 'Access', 'Security']),
            'user_id' => User::factory(),
            'assigned_to' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ticket $ticket): void {
            if ($ticket->ticket_category_id !== null) {
                return;
            }
            $name = (string) $ticket->category;
            $id = TicketCategory::query()
                ->where('name', $name)
                ->whereNull('parent_id')
                ->value('id') ?? TicketCategory::query()->where('name', $name)->value('id');
            if ($id !== null) {
                $ticket->ticket_category_id = $id;
            }
        });
    }
}
