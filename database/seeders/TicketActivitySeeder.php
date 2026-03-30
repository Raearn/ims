<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TicketActivitySeeder extends Seeder
{
    private const STATUS_FLOWS = [
        'Open' => [],
        'In Progress' => [['Open', 'In Progress']],
        'On Hold' => [['Open', 'In Progress'], ['In Progress', 'On Hold']],
        'Resolved' => [['Open', 'In Progress'], ['In Progress', 'Resolved']],
        'Closed' => [['Open', 'In Progress'], ['In Progress', 'Resolved'], ['Resolved', 'Closed']],
    ];

    private const COMMENT_BODIES = [
        'Confirmed the issue on my end. It started appearing after the latest deployment.',
        'Checked the logs — seeing repeated EHOSTUNREACH errors. Likely a routing or DNS issue.',
        'Restarting the service temporarily resolves it, but the problem returns within the hour.',
        'This is affecting at least five users on the same subnet.',
        'Escalated to the network team. Awaiting their response.',
        'Can someone confirm if this also occurs on wired connections, or only over Wi-Fi?',
        'Tested on both — same outcome. Appears to be a server-side configuration issue.',
        'Applied the hotfix from KB5014697. Will monitor for the next 24 hours.',
        'Issue appears resolved after the firmware update was pushed to the affected device.',
        'Note: this was triggered by the scheduled maintenance window that ran Sunday night.',
        'Could this be related to the IP pool exhaustion we flagged last month?',
        'Assigned to Tier 2. SLA timer is running — need a status update by end of day.',
        'User is still experiencing the issue intermittently. Further investigation required.',
        'Temporary workaround: use the secondary access point in the hallway on floor 2.',
        'Closing this out — confirmed fixed by the end user. Thanks for the quick turnaround.',
    ];

    /**
     * Run the database seeds.
     * Clears any previous activity records, then generates a realistic lifecycle
     * for every ticket in the database.
     */
    public function run(): void
    {
        TicketActivity::query()->delete();

        $tickets = Ticket::with('reporter', 'handlers')->get();

        if ($tickets->isEmpty()) {
            $this->command->warn('No tickets found — run DatabaseSeeder or WeeklyDataSeeder first.');

            return;
        }

        $users = User::all();
        $admins = $users->where('role', 'admin');
        $technicians = $users->whereIn('role', ['technical', 'admin']);

        $fallbackUser = $admins->first() ?? $users->first();

        // ── System-level events ───────────────────────────────────────────────
        $this->seedLoginEvents($users);
        $this->seedUserManagementEvents($admins, $users, $fallbackUser);

        // ── Per-ticket lifecycle ──────────────────────────────────────────────
        foreach ($tickets as $ticket) {
            $this->seedTicketLifecycle($ticket, $users, $technicians, $fallbackUser);
        }

        $count = TicketActivity::query()->count();
        $this->command->info("TicketActivitySeeder: created {$count} activity records across {$tickets->count()} tickets.");
    }

    /**
     * Seed realistic login / logout pairs for every user over the past 30 days.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedLoginEvents(Collection $users): void
    {
        $cutoff = now()->subDays(30);

        foreach ($users as $user) {
            $loginCount = fake()->numberBetween(3, 15);

            for ($i = 0; $i < $loginCount; $i++) {
                $loginAt = fake()->dateTimeBetween($cutoff, 'now');
                $at = Carbon::instance($loginAt);

                TicketActivity::create([
                    'ticket_id' => null,
                    'user_id' => $user->id,
                    'action' => 'user_login',
                    'old_value' => null,
                    'new_value' => $user->name,
                    'created_at' => $at,
                ]);

                // Most sessions end with an explicit logout (80 %)
                if (fake()->boolean(80)) {
                    $sessionMinutes = fake()->numberBetween(5, 480);
                    TicketActivity::create([
                        'ticket_id' => null,
                        'user_id' => $user->id,
                        'action' => 'user_logout',
                        'old_value' => null,
                        'new_value' => $user->name,
                        'created_at' => $at->copy()->addMinutes($sessionMinutes),
                    ]);
                }
            }
        }
    }

    /**
     * Seed a handful of historical user-management events performed by admins.
     *
     * @param  Collection<int, User>  $admins
     * @param  Collection<int, User>  $users
     */
    private function seedUserManagementEvents(Collection $admins, Collection $users, User $fallbackUser): void
    {
        $admin = $admins->first() ?? $fallbackUser;
        $cutoff = now()->subDays(30);

        // A few "user created" events
        $users->random(min(3, $users->count()))->each(function (User $u) use ($admin, $cutoff) {
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => $admin->id,
                'action' => 'user_created',
                'old_value' => null,
                'new_value' => "{$u->name} ({$u->role})",
                'created_at' => fake()->dateTimeBetween($cutoff, 'now'),
            ]);
        });

        // A few "user updated" events
        $users->random(min(4, $users->count()))->each(function (User $u) use ($admin, $cutoff) {
            $changes = fake()->randomElement([
                'role: technical → admin',
                "name: Old Name → {$u->name}",
                'email changed',
                'password changed',
            ]);
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => $admin->id,
                'action' => 'user_updated',
                'old_value' => $u->name,
                'new_value' => $changes,
                'created_at' => fake()->dateTimeBetween($cutoff, 'now'),
            ]);
        });
    }

    private function seedTicketLifecycle(
        Ticket $ticket,
        Collection $users,
        Collection $technicians,
        User $fallbackUser,
    ): void {
        $cursor = Carbon::parse($ticket->created_at);
        $reporter = $ticket->reporter ?? $fallbackUser;
        $admin = $ticket->handlers->first() ?? $fallbackUser;

        // ── 1. Ticket created ─────────────────────────────────────────────────
        $this->log($ticket, $reporter, 'created', null, $ticket->title, $cursor->copy());

        // ── 2. Priority change (35 % of tickets) ─────────────────────────────
        if (fake()->boolean(35)) {
            $priorities = ['Low', 'Medium', 'High', 'Critical'];
            $oldPriority = fake()->randomElement(array_diff($priorities, [$ticket->priority]));
            $cursor->addMinutes(fake()->numberBetween(10, 90));
            $this->log($ticket, $admin, 'priority_changed', $oldPriority, $ticket->priority, $cursor->copy());
        }

        // ── 3. Status transitions ─────────────────────────────────────────────
        $flow = self::STATUS_FLOWS[$ticket->status] ?? [];
        foreach ($flow as [$from, $to]) {
            $cursor->addMinutes(fake()->numberBetween(30, 480));

            // Handler assigned when moving to "In Progress"
            if ($to === 'In Progress' && $technicians->isNotEmpty()) {
                $handler = $technicians->random();
                $this->log($ticket, $admin, 'handler_assigned', null, $handler->name, $cursor->copy()->subMinutes(5));
            }

            $this->log($ticket, $admin, 'status_changed', $from, $to, $cursor->copy());

            // Optional handler reassignment during In Progress (20 %)
            if ($to === 'In Progress' && fake()->boolean(20) && $technicians->count() >= 2) {
                $cursor->addMinutes(fake()->numberBetween(60, 300));
                $removedHandler = $technicians->random();
                $newHandler = $technicians->reject(fn ($u) => $u->id === $removedHandler->id)->random();
                $this->log($ticket, $admin, 'handler_removed', $removedHandler->name, null, $cursor->copy()->subMinutes(2));
                $this->log($ticket, $admin, 'handler_assigned', null, $newHandler->name, $cursor->copy());
            }
        }

        // ── 4. Solution added for resolved / closed tickets ───────────────────
        if (in_array($ticket->status, ['Resolved', 'Closed'], true)) {
            $cursor->addMinutes(fake()->numberBetween(5, 60));
            $solutions = [
                'Replaced the faulty network cable on port 14. Connectivity restored.',
                'Rolled back the Windows update (KB5028185) and re-applied a clean install.',
                'Reconfigured the DHCP scope to widen the available IP pool.',
                'Cleared the printer queue and updated the driver to version 5.4.2.',
                'Reset Active Directory password policies and unlocked the affected account.',
                'Pushed a hotfix to the application server and restarted dependent services.',
                'Updated SSL certificate — expired certificate replaced with 2-year renewal.',
                'Adjusted firewall rules to allow outbound SMTP on port 587.',
            ];
            $this->log($ticket, $admin, 'solution_updated', null, fake()->randomElement($solutions), $cursor->copy());
        }

        // ── 5. Comments ───────────────────────────────────────────────────────
        $commentCount = fake()->numberBetween(2, 5);
        $pinnedIndex = fake()->numberBetween(0, $commentCount - 1);
        $deletedIndex = fake()->boolean(30) ? fake()->numberBetween(0, $commentCount - 1) : -1;
        $commentCursor = Carbon::parse($ticket->created_at)->addMinutes(15);

        for ($i = 0; $i < $commentCount; $i++) {
            $commentCursor->addMinutes(fake()->numberBetween(10, 120));
            $commenter = $users->random();
            $bodySnippet = substr(strip_tags(fake()->randomElement(self::COMMENT_BODIES)), 0, 80);

            if ($i === $deletedIndex) {
                // Post then delete
                $this->log($ticket, $commenter, 'comment_posted', null, $bodySnippet, $commentCursor->copy());
                $commentCursor->addMinutes(fake()->numberBetween(5, 30));
                $this->log($ticket, $commenter, 'comment_deleted', $bodySnippet, null, $commentCursor->copy());
            } else {
                $this->log($ticket, $commenter, 'comment_posted', null, $bodySnippet, $commentCursor->copy());

                // Reactions (50 % chance)
                if (fake()->boolean(50)) {
                    $emojis = ['👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅', '👀', '💯'];
                    $reactor = $users->reject(fn ($u) => $u->id === $commenter->id)->random();
                    $emoji = fake()->randomElement($emojis);
                    $commentCursor->addMinutes(fake()->numberBetween(2, 20));
                    $this->log($ticket, $reactor, 'reaction_added', null, "{$emoji} on: {$bodySnippet}", $commentCursor->copy());

                    // Occasionally remove the reaction (25 %)
                    if (fake()->boolean(25)) {
                        $commentCursor->addMinutes(fake()->numberBetween(5, 60));
                        $this->log($ticket, $reactor, 'reaction_removed', "{$emoji} on: {$bodySnippet}", null, $commentCursor->copy());
                    }
                }

                // Pin the chosen comment
                if ($i === $pinnedIndex) {
                    $commentCursor->addMinutes(fake()->numberBetween(5, 30));
                    $this->log($ticket, $admin, 'comment_pinned', null, $bodySnippet, $commentCursor->copy());

                    // Unpin (30 %)
                    if (fake()->boolean(30)) {
                        $commentCursor->addMinutes(fake()->numberBetween(10, 60));
                        $this->log($ticket, $admin, 'comment_unpinned', $bodySnippet, null, $commentCursor->copy());
                    }
                }
            }
        }
    }

    private function log(
        Ticket $ticket,
        User $user,
        string $action,
        ?string $oldValue,
        ?string $newValue,
        Carbon $at,
    ): void {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => $at,
        ]);
    }
}
