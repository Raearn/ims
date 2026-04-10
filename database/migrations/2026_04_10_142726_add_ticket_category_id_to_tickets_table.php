<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('ticket_category_id')
                ->nullable()
                ->after('category')
                ->constrained('ticket_categories')
                ->restrictOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $pairs = DB::table('tickets')
                ->select('tickets.id as ticket_id', 'ticket_categories.id as category_id')
                ->join('ticket_categories', function ($join): void {
                    $join->on('tickets.category', '=', 'ticket_categories.name')
                        ->whereNull('ticket_categories.parent_id');
                })
                ->get();

            foreach ($pairs as $pair) {
                DB::table('tickets')
                    ->where('id', $pair->ticket_id)
                    ->update(['ticket_category_id' => $pair->category_id]);
            }
        } else {
            DB::statement('
                UPDATE tickets
                INNER JOIN ticket_categories
                    ON tickets.category = ticket_categories.name
                    AND ticket_categories.parent_id IS NULL
                SET tickets.ticket_category_id = ticket_categories.id
            ');
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_category_id');
        });
    }
};
