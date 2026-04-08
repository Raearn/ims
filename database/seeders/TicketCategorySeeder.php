<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (TicketConfigDefaults::categories() as $index => $category) {
            TicketCategory::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['sort_order' => $index])
            );
        }
    }
}
