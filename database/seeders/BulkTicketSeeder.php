<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkTicketSeeder extends Seeder
{
    private const TICKET_COUNT = 300;

    private const STATUSES = ['Open', 'In Progress', 'On Hold', 'Resolved', 'Cancelled'];

    private const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    private const CATEGORIES = ['Network', 'Hardware', 'Software', 'Access', 'Security'];

    private const EMOJIS = ['👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅', '👀', '💯'];

    private const TICKET_TITLES = [
        // Network
        'Network switch keeps dropping connections intermittently',
        'VPN client fails to connect after recent Windows update',
        'Wi-Fi dead zone discovered in Building C conference rooms',
        'Intermittent packet loss on core switch uplink',
        'DNS resolution failing for internal hostnames',
        'DHCP pool exhaustion causing address conflicts',
        'Network latency spikes during business hours',
        'Firewall blocking outbound SMTP on port 587',
        'VLAN misconfiguration isolating finance workstations',
        'Remote desktop sessions dropping every 30 minutes',
        'Bandwidth saturation on WAN link during backups',
        'Network printer unreachable after IP change',
        'SD-WAN failover not triggering on link failure',
        'QoS policy not prioritizing VoIP traffic',
        'SSL VPN certificate expired – users cannot connect',
        // Hardware
        'CPU temperature spike on production server rack',
        'RAID array degraded – single disk failure detected',
        'Laptop screen flickering when docked to external monitor',
        'Power outage caused NAS appliance to go offline',
        'Keyboard unresponsive after liquid spill incident',
        'Workstation BSOD loop after Windows driver update',
        'USB ports disabled on engineering floor workstations',
        'Printer paper jam sensor malfunctioning on Floor 3',
        'UPS battery health critical – replacement needed',
        'Hard drive clicking noise on finance workstation',
        'Projector in boardroom not detected via HDMI',
        'RAM failure on development server DB-02',
        'Desktop fan making loud grinding noise',
        'Barcode scanner not recognized after USB reconnect',
        'SAN storage array reporting offline path',
        // Software
        'ERP module crashes on report generation',
        'Microsoft 365 activation failing for new accounts',
        'Antivirus flagging internal deployment tool as threat',
        'Browser extension blocking intranet portal access',
        'Python environment conflict breaking build pipeline',
        'License server unreachable – CAD software offline',
        'Slack notifications not delivered on Windows 11',
        'PDF export generates blank pages in legacy app',
        'Database connection pool exhausted during peak load',
        'Node.js version mismatch breaking CI pipeline',
        'Outlook calendar sync broken after Exchange migration',
        'Software update loop preventing system startup',
        'Screen recording tool crashes on multi-monitor setup',
        'Docker containers failing to start after host reboot',
        'SSH key authentication rejected on jump server',
        // Access
        'New employee workstation setup and account provisioning',
        'Cannot access shared drive after password policy reset',
        'Active Directory account locked repeatedly – auto lockout',
        'MFA token not sending SMS to new mobile number',
        'Group policy preventing software installation request',
        'File share permissions too restrictive for design team',
        'Password reset portal returning 500 error',
        'Guest Wi-Fi access not working in lobby area',
        'Admin access request for deployment server DSV-01',
        'Role-based access review – quarterly audit flag',
        'Contractor onboarding blocked by AD provisioning delay',
        'VPN split tunnelling policy breaking internal DNS',
        'Two-factor auth app lost – employee phone replaced',
        'Privileged access request to production database',
        'SharePoint site permissions misconfigured for HR team',
        // Security
        'Suspicious login attempt from unknown IP detected',
        'CCTV camera feed not loading on security dashboard',
        'Phishing email reported by multiple users – Finance dept',
        'Domain controller replication lag detected',
        'SSL certificate expiry warning on customer portal',
        'Endpoint sending abnormal outbound traffic volume',
        'Ransomware alert triggered on workstation WS-145',
        'Security patch missing on 12 endpoints after Patch Tuesday',
        'Privileged account used outside business hours',
        'Data exfiltration alert – USB mass storage device',
        'Firewall rule change requested for new SaaS application',
        'Web application WAF blocking legitimate API calls',
        'Malware detected in email attachment – Finance inbox',
        'CVE-2025-1234 remediation required on web servers',
        'SOC alert: lateral movement detected on internal subnet',
    ];

    private const COMMENT_BODIES = [
        '<p>Confirmed the issue on my end. It started appearing right after the patch was deployed on Monday.</p>',
        '<p>Checked the event logs — seeing repeated <code>EHOSTUNREACH</code> errors every few minutes. Likely a routing table issue.</p>',
        '<p>Restarting the affected service temporarily resolves it, but the problem returns within the hour.</p>',
        '<p>This is impacting at least 5 users on the same subnet. Finance team is particularly affected.</p>',
        '<p>Escalated to the infrastructure team. Awaiting their initial assessment.</p>',
        '<p>Can someone confirm if this also occurs on wired connections, or only over Wi-Fi?</p>',
        '<p>Tested on both wired and wireless — same result. Appears to be a server-side issue.</p>',
        '<p>Applied the hotfix from KB5014697. Will monitor the affected machines for the next 24 hours.</p>',
        '<p>Issue appears resolved after pushing the firmware update to the affected device. Monitoring for recurrence.</p>',
        '<p>Note: this was triggered by the scheduled maintenance window that ran Sunday night between 01:00–03:00.</p>',
        '<p>Could this be related to the IP pool exhaustion issue we flagged last month? Worth investigating.</p>',
        '<p>Assigned to Tier 2 support. SLA timer is running — we need a status update by end of day.</p>',
        '<p>User is still experiencing the issue intermittently. Further investigation required before closing.</p>',
        '<p>Temporary workaround: use the secondary access point in the hallway near Room 204.</p>',
        '<p>Closing this ticket — confirmed fixed by the end user. Thanks for the quick turnaround!</p>',
        '<p>Ran a full diagnostic — no hardware faults detected. The issue seems software-related.</p>',
        '<p>Cross-referenced with the change log and found a config push that correlates with the start time.</p>',
        '<p>Rolled back the configuration change. Users should test and confirm whether the issue persists.</p>',
        '<p>IT Security has been notified. They will conduct a post-incident review by end of week.</p>',
        '<p>This matches a known bug in version 3.4.1 of the application. Vendor patch is expected next Tuesday.</p>',
        '<p>Created a workaround script that bypasses the broken module. Shared via internal Wiki.</p>',
        '<p>Monitoring shows the error rate dropping after the config change. Looking stable so far.</p>',
        '<p>Requested hardware replacement through procurement. ETA is 3–5 business days.</p>',
        '<p>User training scheduled — this appears to be a recurring issue caused by manual misconfiguration.</p>',
        '<p>Patch applied successfully on all affected endpoints. Compliance dashboard now showing green.</p>',
    ];

    private const REPLY_BODIES = [
        '<p>Thanks for the update. I\'ll keep monitoring on my end and report back if anything changes.</p>',
        '<p>Confirmed on my machine too. Let me know if you need additional logs or screenshots.</p>',
        '<p>Good catch — I\'ll update the runbook to include this scenario for future reference.</p>',
        '<p>Agreed. I\'ll flag this as a known issue in the tracker so the team is aware going forward.</p>',
        '<p>Can you share the exact error message you\'re seeing? A screenshot would help narrow it down.</p>',
        '<p>That hotfix worked for us as well. Marking as confirmed resolved on my end.</p>',
        '<p>Same issue here too. Upvoting for priority — this is blocking several projects.</p>',
        '<p>Appreciate the workaround — it\'s helping the team stay productive in the meantime.</p>',
        '<p>Will follow up first thing tomorrow morning after checking overnight logs.</p>',
        '<p>The firmware rollback option is also available if needed. I can prepare the rollback script.</p>',
        '<p>Checked with the vendor — they have a patch in the pipeline, estimated 3-day turnaround.</p>',
        '<p>Added this to the incident report for the weekly ops review meeting on Friday.</p>',
    ];

    private const SOLUTIONS = [
        'Replaced the faulty network cable on switch port 14. Connectivity fully restored.',
        'Rolled back Windows update KB5028185 and applied a clean driver reinstallation.',
        'Reconfigured the DHCP scope to expand the available address pool to /22.',
        'Cleared the stuck print queue and updated the printer driver to version 5.4.2.',
        'Reset Active Directory password policies and manually unlocked the affected account.',
        'Pushed a hotfix to the application server and restarted all dependent microservices.',
        'Renewed the expired SSL certificate and pushed to load balancer. Deployed successfully.',
        'Updated firewall rules to allow outbound traffic on port 587 for approved mail servers.',
        'Corrected VLAN tagging on the access switch and verified end-to-end connectivity.',
        'Applied vendor patch v3.4.2 which addresses the race condition causing the crashes.',
        'Replaced failed hard drive and rebuilt RAID array. Data integrity verified.',
        'Re-provisioned Active Directory account with correct group memberships and permissions.',
    ];

    public function run(): void
    {
        $this->command->info('BulkTicketSeeder: starting…');

        $users = User::all();
        $admins = $users->where('role', 'admin');
        $technicians = $users->whereIn('role', ['technical', 'admin']);

        if ($users->isEmpty()) {
            $this->command->error('No users found. Run DatabaseSeeder first.');

            return;
        }

        $fallbackUser = $admins->first() ?? $users->first();
        $now = now();
        $windowStart = $now->copy()->subDays(90);

        // Build batched inserts for performance
        $ticketRows = [];
        $commentRows = [];
        $reactionRows = [];

        // Pre-generate all tickets
        $titles = self::TICKET_TITLES;
        $titleCount = count($titles);

        $this->command->info('Preparing '.self::TICKET_COUNT.' tickets…');

        for ($t = 0; $t < self::TICKET_COUNT; $t++) {
            $status = fake()->randomElement(self::STATUSES);
            $createdAt = fake()->dateTimeBetween($windowStart, $now);
            $updatedAt = fake()->dateTimeBetween($createdAt, $now);

            $ticketRows[] = [
                'title' => $titles[$t % $titleCount].' (#'.($t + 1).')',
                'description' => '<p>'.fake()->paragraph(3).'</p><p>'.fake()->paragraph(2).'</p>',
                'status' => $status,
                'priority' => fake()->randomElement(self::PRIORITIES),
                'category' => fake()->randomElement(self::CATEGORIES),
                'solution' => in_array($status, ['Resolved', 'Cancelled'])
                    ? fake()->randomElement(self::SOLUTIONS)
                    : null,
                'user_id' => $users->random()->id,
                'resolved_at' => in_array($status, ['Resolved', 'Cancelled'])
                    ? fake()->dateTimeBetween($createdAt, $now)
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        // Chunk-insert tickets (bypasses observer — no duplicate activity rows)
        foreach (array_chunk($ticketRows, 100) as $chunk) {
            Ticket::withoutEvents(fn () => Ticket::insert($chunk));
        }

        // Reload all newly created tickets to get their IDs
        // Use the highest IDs = the ones we just inserted
        $newTickets = Ticket::latest('id')->limit(self::TICKET_COUNT)->get()->reverse()->values();

        $this->command->info('Assigning handlers and seeding comments…');

        // Handler pivot rows
        $handlerPivot = [];

        foreach ($newTickets as $ticket) {
            // Assign 1–3 handlers to non-Open tickets
            if ($ticket->status !== 'Open' && $technicians->isNotEmpty()) {
                $count = fake()->numberBetween(1, min(3, $technicians->count()));
                $handlers = $technicians->random($count);
                foreach ($handlers as $handler) {
                    $handlerPivot[] = [
                        'ticket_id' => $ticket->id,
                        'user_id' => $handler->id,
                    ];
                }
            }

            // 2–6 top-level comments per ticket
            $commentCount = fake()->numberBetween(2, 6);
            $commentCursor = Carbon::parse($ticket->created_at)->addMinutes(fake()->numberBetween(10, 60));

            for ($c = 0; $c < $commentCount; $c++) {
                $commentCursor->addMinutes(fake()->numberBetween(15, 240));
                if ($commentCursor->gt($now)) {
                    break;
                }

                $commenterId = $users->random()->id;
                $body = fake()->randomElement(self::COMMENT_BODIES);

                $commentRows[] = [
                    'ticket_id' => $ticket->id,
                    'user_id' => $commenterId,
                    'parent_id' => null,
                    'is_pinned' => $c === 0 && fake()->boolean(20),
                    'body' => $body,
                    'created_at' => $commentCursor->copy(),
                    'updated_at' => $commentCursor->copy(),
                    '_meta_key' => count($commentRows), // temp index for reply linking
                ];
            }
        }

        // Chunk-insert handler pivot
        foreach (array_chunk($handlerPivot, 200) as $chunk) {
            DB::table('ticket_handlers')->insertOrIgnore($chunk);
        }

        // Chunk-insert comments (bypass observer to avoid double activity rows)
        $commentMetas = $commentRows;
        $cleanRows = array_map(function ($row) {
            unset($row['_meta_key']);

            return $row;
        }, $commentRows);

        foreach (array_chunk($cleanRows, 200) as $chunk) {
            TicketComment::withoutEvents(fn () => TicketComment::insert($chunk));
        }

        $this->command->info('Seeding replies and reactions…');

        // Reload inserted top-level comments to get real IDs
        $insertedCommentIds = TicketComment::whereNull('parent_id')
            ->whereIn('ticket_id', $newTickets->pluck('id'))
            ->pluck('id')
            ->toArray();

        // Add replies (0–3 per comment) and reactions
        $replyRows = [];

        foreach ($insertedCommentIds as $parentId) {
            // Reactions on this comment (0–4 users each with a random emoji)
            if (fake()->boolean(60)) {
                $reactors = $users->random(fake()->numberBetween(1, min(4, $users->count())));
                $usedEmoji = [];
                foreach ($reactors as $reactor) {
                    $emoji = fake()->randomElement(self::EMOJIS);
                    // One reaction per user per emoji per comment
                    $key = $parentId.'-'.$reactor->id.'-'.$emoji;
                    if (! in_array($key, $usedEmoji)) {
                        $usedEmoji[] = $key;
                        $reactionRows[] = [
                            'comment_id' => $parentId,
                            'user_id' => $reactor->id,
                            'emoji' => $emoji,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // Replies
            $replyCount = fake()->numberBetween(0, 3);
            for ($r = 0; $r < $replyCount; $r++) {
                $replyRows[] = [
                    'ticket_id' => null, // will be filled below
                    'parent_id' => $parentId,
                    'user_id' => $users->random()->id,
                    'is_pinned' => false,
                    'body' => fake()->randomElement(self::REPLY_BODIES),
                    'created_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
                    'updated_at' => now(),
                    '_parent_id' => $parentId,
                ];
            }
        }

        // Fill in ticket_id for replies from parent comment
        if (! empty($replyRows)) {
            $parentTicketMap = TicketComment::whereIn('id', $insertedCommentIds)
                ->pluck('ticket_id', 'id')
                ->toArray();

            $replyRows = array_map(function ($row) use ($parentTicketMap) {
                $row['ticket_id'] = $parentTicketMap[$row['_parent_id']] ?? null;
                unset($row['_parent_id']);

                return $row;
            }, array_filter($replyRows, fn ($r) => isset($parentTicketMap[$r['_parent_id']])));

            foreach (array_chunk($replyRows, 200) as $chunk) {
                TicketComment::withoutEvents(fn () => TicketComment::insert($chunk));
            }
        }

        // Insert reactions (de-duplicated by unique constraint)
        foreach (array_chunk($reactionRows, 200) as $chunk) {
            TicketCommentReaction::insertOrIgnore($chunk);
        }

        $this->command->info('Running TicketActivitySeeder to generate activity log…');
        $this->call(TicketActivitySeeder::class);

        $this->command->newLine();
        $this->command->info('BulkTicketSeeder complete:');
        $this->command->table(
            ['Resource', 'Total'],
            [
                ['Tickets',   Ticket::count()],
                ['Comments',  TicketComment::count()],
                ['Reactions', TicketCommentReaction::count()],
                ['Activities', TicketActivity::count()],
            ]
        );
    }
}
