<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketSlaBreached;
use Illuminate\Console\Command;

class CheckSlaBreaches extends Command
{
    /**
     * SLA thresholds in hours per priority level.
     *
     * @var array<string, int>
     */
    protected const SLA_HOURS = [
        'Critical' => 4,
        'High' => 8,
        'Medium' => 24,
        'Low' => 72,
    ];

    protected $signature = 'tickets:check-sla';

    protected $description = 'Notify handlers and admins about tickets that have breached their SLA.';

    public function handle(): void
    {
        $admins = User::where('role', 'admin')->get();

        foreach (self::SLA_HOURS as $priority => $hours) {
            $breachedTickets = Ticket::with(['handlers', 'reporter'])
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->where('priority', $priority)
                ->where('created_at', '<=', now()->subHours($hours))
                ->get();

            foreach ($breachedTickets as $ticket) {
                $notified = collect();

                // Notify each assigned handler
                foreach ($ticket->handlers as $handler) {
                    $handler->notify(new TicketSlaBreached($ticket));
                    $notified->push($handler->id);
                }

                // Notify admins (avoid duplicate if they are also a handler)
                foreach ($admins as $admin) {
                    if (! $notified->contains($admin->id)) {
                        $admin->notify(new TicketSlaBreached($ticket));
                    }
                }
            }
        }

        $this->info('SLA breach check complete.');
    }
}
