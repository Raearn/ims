<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Network', 'icon' => 'Wifi'],
            ['name' => 'Software', 'icon' => 'MonitorPlay'],
            ['name' => 'Hardware', 'icon' => 'MonitorSmartphone'],
            ['name' => 'Security', 'icon' => 'ShieldAlert'],
            ['name' => 'Access', 'icon' => 'Key'],
            ['name' => 'Others', 'icon' => 'HelpCircle'],
        ];

        foreach ($categories as $index => $category) {
            TicketCategory::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['sort_order' => $index])
            );
        }
    }
}
