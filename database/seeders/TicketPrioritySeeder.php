<?php

namespace Database\Seeders;

use App\Models\TicketPriority;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Seeder;

class TicketPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (TicketConfigDefaults::priorities() as $index => $priority) {
            TicketPriority::updateOrCreate(
                ['name' => $priority['name']],
                [
                    'icon' => $priority['icon'],
                    'color' => $priority['color'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
