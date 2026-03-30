<?php

namespace Database\Seeders;

use App\Enums\TicketStatusHandlerRequirement;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Open', 'icon' => 'AlertTriangle', 'color' => '#f43f5e', 'handler_requirement' => TicketStatusHandlerRequirement::None->value],
            ['name' => 'In Progress', 'icon' => 'Play', 'color' => '#3b82f6', 'handler_requirement' => TicketStatusHandlerRequirement::Required->value],
            ['name' => 'On Hold', 'icon' => 'Pause', 'color' => '#f59e0b', 'handler_requirement' => TicketStatusHandlerRequirement::Required->value],
            ['name' => 'Resolved', 'icon' => 'CheckCircle2', 'color' => '#059669', 'handler_requirement' => TicketStatusHandlerRequirement::Required->value],
            ['name' => 'Closed', 'icon' => 'Ban', 'color' => '#64748b', 'handler_requirement' => TicketStatusHandlerRequirement::Optional->value],
        ];

        foreach ($statuses as $index => $status) {
            TicketStatus::updateOrCreate(
                ['name' => $status['name']],
                array_merge($status, ['sort_order' => $index])
            );
        }
    }
}
