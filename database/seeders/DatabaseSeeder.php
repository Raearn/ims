<?php

namespace Database\Seeders;

use App\Models\Ticket;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->supervisor()->create([
            'name' => 'Supervisor User',
            'email' => 'supervisor@example.com',
        ]);

        User::factory(10)->create(); // Default technical users

        // Create some tickets
        $users = User::all();
        Ticket::factory(20)->create([
            'user_id' => fn () => $users->random()->id,
            'assigned_to' => fn () => fake()->boolean(60) ? $users->random()->id : null,
        ]);

        $this->call(TicketActivitySeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(TicketConfigSeeder::class);
    }
}
