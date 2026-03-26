<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketConfigSeeder extends Seeder
{
    /**
     * Seed the ticket_categories, ticket_priorities, and ticket_statuses tables
     * with sensible defaults. Uses updateOrCreate so it is safe to re-run.
     */
    public function run(): void
    {
        // ── Categories ─────────────────────────────────────────────────
        $categories = [
            ['name' => 'Network',  'icon' => 'Network',    'sort_order' => 0],
            ['name' => 'Hardware', 'icon' => 'HardDrive',  'sort_order' => 1],
            ['name' => 'Software', 'icon' => 'Code',       'sort_order' => 2],
            ['name' => 'Access',   'icon' => 'Key',        'sort_order' => 3],
            ['name' => 'Security', 'icon' => 'Shield',     'sort_order' => 4],
            ['name' => 'Others',   'icon' => 'HelpCircle', 'sort_order' => 5],
        ];

        foreach ($categories as $row) {
            TicketCategory::updateOrCreate(['name' => $row['name']], $row);
        }

        // ── Priorities ─────────────────────────────────────────────────
        $priorities = [
            ['name' => 'Critical', 'icon' => 'AlertCircle',   'color' => '#f43f5e', 'sort_order' => 0],
            ['name' => 'High',     'icon' => 'AlertTriangle', 'color' => '#f97316', 'sort_order' => 1],
            ['name' => 'Medium',   'icon' => 'ArrowUpCircle', 'color' => '#eab308', 'sort_order' => 2],
            ['name' => 'Low',      'icon' => 'Circle',        'color' => '#60a5fa', 'sort_order' => 3],
        ];

        foreach ($priorities as $row) {
            TicketPriority::updateOrCreate(['name' => $row['name']], $row);
        }

        // ── Statuses ───────────────────────────────────────────────────
        $statuses = [
            ['name' => 'Open',        'sort_order' => 0],
            ['name' => 'In Progress', 'sort_order' => 1],
            ['name' => 'On Hold',     'sort_order' => 2],
            ['name' => 'Resolved',    'sort_order' => 3],
            ['name' => 'Closed',      'sort_order' => 4],
        ];

        foreach ($statuses as $row) {
            TicketStatus::updateOrCreate(['name' => $row['name']], $row);
        }

        // ── Remove the now-redundant JSON blobs from settings ──────────
        Setting::whereIn('key', [
            'ticket_categories',
            'ticket_priorities',
            'ticket_statuses',
            'default_ticket_priority',
        ])->delete();
    }
}
