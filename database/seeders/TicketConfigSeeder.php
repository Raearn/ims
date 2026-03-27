<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Seeder;

class TicketConfigSeeder extends Seeder
{
    /**
     * Seed the ticket_categories, ticket_priorities, and ticket_statuses tables
     * with sensible defaults. Uses updateOrCreate so it is safe to re-run.
     */
    public function run(): void
    {
        foreach (TicketConfigDefaults::categories() as $index => $row) {
            TicketCategory::updateOrCreate(
                ['name' => $row['name']],
                ['icon' => $row['icon'], 'sort_order' => $index],
            );
        }

        foreach (TicketConfigDefaults::priorities() as $index => $row) {
            TicketPriority::updateOrCreate(
                ['name' => $row['name']],
                [
                    'icon' => $row['icon'],
                    'color' => $row['color'],
                    'sort_order' => $index,
                ],
            );
        }

        foreach (TicketConfigDefaults::statuses() as $index => $row) {
            TicketStatus::updateOrCreate(
                ['name' => $row['name']],
                [
                    'icon' => $row['icon'],
                    'color' => $row['color'],
                    'handler_requirement' => $row['handler_requirement'],
                    'sort_order' => $index,
                ],
            );
        }

        Setting::whereIn('key', [
            'ticket_categories',
            'ticket_priorities',
            'ticket_statuses',
            'default_ticket_priority',
        ])->delete();
    }
}
