<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
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
            'status' => fake()->randomElement(['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed']),
            'priority' => fake()->randomElement(['Low', 'Medium', 'High', 'Critical']),
            'category' => fake()->randomElement(['Network', 'Hardware', 'Software', 'Access', 'Security']),
            'user_id' => User::factory(),
            'assigned_to' => fake()->boolean(70) ? User::factory() : null,
        ];
    }
}
