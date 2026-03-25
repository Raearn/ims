<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            // Drop the existing NOT NULL foreign key constraint
            $table->dropForeign(['ticket_id']);

            // Allow NULL so system-level events (login, user management, etc.)
            // can be stored without a ticket reference
            $table->unsignedBigInteger('ticket_id')->nullable()->change();

            // Re-add the foreign key — cascades on ticket deletion, nulls on nothing
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->unsignedBigInteger('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }
};
