<?php

namespace Database\Seeders;

use App\Models\TicketStatus;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (TicketConfigDefaults::statuses() as $index => $status) {
            TicketStatus::updateOrCreate(
                ['name' => $status['name']],
                [
                    'icon' => $status['icon'],
                    'color' => $status['color'],
                    'handler_requirement' => $status['handler_requirement'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
