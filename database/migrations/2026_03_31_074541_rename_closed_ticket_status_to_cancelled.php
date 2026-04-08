<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tickets')->where('status', 'Closed')->update(['status' => 'Cancelled']);
        DB::table('ticket_statuses')->where('name', 'Closed')->update(['name' => 'Cancelled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tickets')->where('status', 'Cancelled')->update(['status' => 'Closed']);
        DB::table('ticket_statuses')->where('name', 'Cancelled')->update(['name' => 'Closed']);
    }
};
