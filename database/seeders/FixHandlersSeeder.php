<?php

namespace Database\Seeders;

use App\Models\Ticket;
use Illuminate\Database\Seeder;

class FixHandlersSeeder extends Seeder
{
    public function run(): void
    {
        $tickets = Ticket::whereNotNull('assigned_to')->get();
        foreach ($tickets as $ticket) {
            $ticket->handlers()->syncWithoutDetaching([$ticket->assigned_to]);
        }

        $this->command->info('Fixed '.$tickets->count().' ticket handlers.');
    }
}
