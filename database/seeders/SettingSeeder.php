<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            // ── General ───────────────────────────────────────────────
            [
                'key' => 'app_name',
                'value' => 'IMS – Incident Management System',
                'type' => 'string',
                'group' => 'general',
            ],
            [
                'key' => 'app_description',
                'value' => 'Internal helpdesk and incident tracking platform.',
                'type' => 'string',
                'group' => 'general',
            ],
            [
                'key' => 'contact_email',
                'value' => 'support@example.com',
                'type' => 'string',
                'group' => 'general',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'general',
            ],
            [
                'key' => 'allow_registration',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'general',
            ],
            // ── Tickets ───────────────────────────────────────────────
            [
                'key' => 'auto_close_resolved_after_days',
                'value' => '7',
                'type' => 'integer',
                'group' => 'tickets',
            ],
            // ── Appearance ────────────────────────────────────────────
            [
                'key' => 'default_theme',
                'value' => 'system',
                'type' => 'string',
                'group' => 'appearance',
            ],
            [
                'key' => 'sidebar_collapsed_default',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'appearance',
            ],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
