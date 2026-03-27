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
