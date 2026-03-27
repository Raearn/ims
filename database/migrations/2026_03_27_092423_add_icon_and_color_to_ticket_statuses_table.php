<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->string('icon', 100)->default('Circle')->after('name');
            $table->string('color', 20)->default('#64748b')->after('icon');
        });

        // Hex values = Tailwind default palette (matches pre-config Tickets.vue status badges).
        $defaults = [
            'Open' => ['icon' => 'AlertTriangle', 'color' => '#f43f5e'],
            'In Progress' => ['icon' => 'Play', 'color' => '#3b82f6'],
            'On Hold' => ['icon' => 'Pause', 'color' => '#f59e0b'],
            'Resolved' => ['icon' => 'CheckCircle2', 'color' => '#059669'],
            'Closed' => ['icon' => 'Ban', 'color' => '#64748b'],
        ];

        foreach ($defaults as $name => $row) {
            DB::table('ticket_statuses')->where('name', $name)->update($row);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
