<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyDataSeeder extends Seeder
{
    /**
     * Seed tickets, comments, and replies all within the current calendar week.
     * Safe to run repeatedly — only creates new records, never truncates.
     */
    public function run(): void
    {
        $weekStart = now()->startOfWeek();   // Monday 00:00
        $weekEnd = now();                  // right now (capped at present)

        $users = User::all();
        $reporters = $users->whereIn('role', ['technical', 'admin', 'supervisor']);
        $technicians = $users->whereIn('role', ['technical', 'admin']);

        // ── 1. Tickets ───────────────────────────────────────────────────────
        $statuses = ['Open', 'In Progress', 'On Hold', 'Resolved', 'Cancelled'];
        $priorities = ['Low', 'Medium', 'High', 'Critical'];
        $categories = ['Network', 'Hardware', 'Software', 'Access', 'Security'];

        $ticketTitles = [
            'Network switch keeps dropping connections',
            'VPN client fails after Windows update',
            'Printer on Floor 3 is offline',
            'Cannot access shared drive after password reset',
            'CPU temperature spike on server rack',
            'Email delivery delays reported by finance team',
            'SSL certificate expiry warning on portal',
            'New employee workstation setup request',
            'CCTV camera feed not loading on dashboard',
            'Database backup job failed overnight',
            'Wi-Fi dead zone in conference room B',
            'Laptop screen flickering on docking station',
            'Active Directory account locked repeatedly',
            'Software license expired for design team',
            'Firewall blocking outbound SMTP traffic',
            'USB ports disabled on engineering workstations',
            'Remote desktop latency complaints',
            'Power outage caused NAS to go offline',
            'Antivirus flagging internal deployment tool',
            'Domain controller replication lag detected',
        ];

        $tickets = collect();

        foreach ($ticketTitles as $i => $title) {
            $createdAt = fake()->dateTimeBetween($weekStart, $weekEnd);
            $status = fake()->randomElement($statuses);
            $reporter = $reporters->random();

            /** @var Ticket $ticket */
            $ticket = Ticket::create([
                'title' => $title,
                'description' => '<p>'.fake()->paragraph(3).'</p><p>'.fake()->paragraph(2).'</p>',
                'status' => $status,
                'priority' => $priorities[$i % count($priorities)],
                'category' => fake()->randomElement($categories),
                'user_id' => $reporter->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Assign 1–3 handlers when not Open
            if ($status !== 'Open') {
                $handlerCount = fake()->numberBetween(1, 3);
                $ticket->handlers()->sync(
                    $technicians->random(min($handlerCount, $technicians->count()))->pluck('id')->toArray()
                );
            }

            $tickets->push($ticket);
        }

        // ── 2. Comments & Replies ─────────────────────────────────────────────
        $commentBodies = [
            '<p>I have confirmed the issue on my end as well. It started happening after the latest patch.</p>',
            '<p>Checked the logs — seeing <code>EHOSTUNREACH</code> errors every few minutes. Likely a routing issue.</p>',
            '<p>Restarting the service temporarily resolves it but the problem comes back within an hour.</p>',
            '<p>This is affecting at least 5 users on the same subnet.</p>',
            '<p>I\'ve escalated this to the network team. Waiting for their response.</p>',
            '<p>Can someone confirm if this also happens on wired connections or only Wi-Fi?</p>',
            '<p>Tested on both — same result. Seems to be a server-side issue.</p>',
            '<p>Applied the hotfix from KB5014697. Will monitor for 24 hours.</p>',
            '<p>The issue appears to be resolved after the firmware update was pushed.</p>',
            '<p>Adding a note: this was triggered by the scheduled maintenance window on Sunday.</p>',
            '<p>Could this be related to the IP pool exhaustion we saw last month?</p>',
            '<p>Assigned to Tier 2. SLA timer is running — need an update by EOD.</p>',
            '<p>User is still experiencing the issue intermittently. Needs further investigation.</p>',
            '<p>Temporary workaround: use the secondary access point in the hallway.</p>',
            '<p>Closing this ticket — confirmed fixed by end user. Thanks for the quick turnaround!</p>',
        ];

        $replyBodies = [
            '<p>Thanks for the update. I\'ll keep an eye on it.</p>',
            '<p>Confirmed on my machine too. Let me know if you need additional logs.</p>',
            '<p>Good catch. I\'ll update the runbook to include this scenario.</p>',
            '<p>Agreed — this should be a known issue in the tracker going forward.</p>',
            '<p>Can you share the exact error message you\'re seeing?</p>',
            '<p>That hotfix worked for us. Marking as resolved on my end.</p>',
            '<p>Same issue here. Upvoting for priority.</p>',
            '<p>Appreciate the workaround — it\'s helping the team stay productive in the meantime.</p>',
            '<p>Will follow up first thing tomorrow morning.</p>',
            '<p>The firmware rollback option is also available if needed.</p>',
        ];

        foreach ($tickets as $ticket) {
            $commentCount = fake()->numberBetween(2, 5);
            $ticketCreatedAt = Carbon::parse($ticket->created_at);
            $commentStart = $ticketCreatedAt->copy()->addMinutes(5);

            for ($c = 0; $c < $commentCount; $c++) {
                $commentAt = fake()->dateTimeBetween($commentStart, $weekEnd);

                /** @var TicketComment $comment */
                $comment = TicketComment::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $users->random()->id,
                    'parent_id' => null,
                    'body' => fake()->randomElement($commentBodies),
                    'created_at' => $commentAt,
                    'updated_at' => $commentAt,
                ]);

                // 0–3 replies per comment
                $replyCount = fake()->numberBetween(0, 3);
                $replyStart = Carbon::parse($commentAt)->addMinutes(2);

                for ($r = 0; $r < $replyCount; $r++) {
                    $replyAt = fake()->dateTimeBetween($replyStart, $weekEnd);

                    TicketComment::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $users->random()->id,
                        'parent_id' => $comment->id,
                        'body' => fake()->randomElement($replyBodies),
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);
                }
            }
        }

        $this->command->info('WeeklyDataSeeder: created '.count($ticketTitles).' tickets with comments and replies within the current week.');

        $this->call(TicketActivitySeeder::class);
    }
}
