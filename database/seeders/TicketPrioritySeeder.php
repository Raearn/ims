<?php

namespace Database\Seeders;

use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

class TicketPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            ['name' => 'Low', 'icon' => 'ArrowDown', 'color' => '#3b82f6'],
            ['name' => 'Medium', 'icon' => 'Minus', 'color' => '#f59e0b'],
            ['name' => 'High', 'icon' => 'ArrowUp', 'color' => '#f97316'],
            ['name' => 'Critical', 'icon' => 'AlertTriangle', 'color' => '#ef4444'],
        ];

        foreach ($priorities as $index => $priority) {
            TicketPriority::updateOrCreate(
                ['name' => $priority['name']],
                array_merge($priority, ['sort_order' => $index])
            );
        }
    }
}
