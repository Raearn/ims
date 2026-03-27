<?php

use App\Enums\TicketStatusHandlerRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->string('handler_requirement', 16)
                ->default(TicketStatusHandlerRequirement::Optional->value)
                ->after('color');
        });

        $map = [
            'Open' => TicketStatusHandlerRequirement::None,
            'In Progress' => TicketStatusHandlerRequirement::Required,
            'On Hold' => TicketStatusHandlerRequirement::Required,
            'Resolved' => TicketStatusHandlerRequirement::Required,
            'Closed' => TicketStatusHandlerRequirement::Optional,
        ];

        foreach ($map as $name => $requirement) {
            DB::table('ticket_statuses')
                ->where('name', $name)
                ->update(['handler_requirement' => $requirement->value]);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropColumn('handler_requirement');
        });
    }
};
