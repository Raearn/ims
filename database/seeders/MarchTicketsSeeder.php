<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\TicketCommentVote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MarchTicketsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run DatabaseSeeder first.');

            return;
        }

        // Generate tickets for March 23 and March 25
        Ticket::factory(200)->make([
            'user_id' => fn () => $users->random()->id,
            'created_at' => function () {
                $isMarch23 = fake()->boolean();
                if ($isMarch23) {
                    $start = Carbon::create(2026, 3, 23, 0, 0, 0);
                    $end = Carbon::create(2026, 3, 23, 23, 59, 59);
                } else {
                    $start = Carbon::create(2026, 3, 25, 0, 0, 0);
                    $end = Carbon::create(2026, 3, 25, 23, 59, 59);
                }

                return fake()->dateTimeBetween($start, $end);
            },
        ])->each(function (Ticket $ticket) use ($users) {
            $ticket->updated_at = clone $ticket->created_at;
            $ticket->save();

            // Handlers logic
            if ($ticket->status !== 'Open') {
                $ticket->handlers()->attach($users->random()->id);
            } else {
                if (fake()->boolean(20)) {
                    $ticket->handlers()->attach($users->random()->id);
                }
            }

            // Create comments
            $numComments = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $numComments; $i++) {
                $comment = TicketComment::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $users->random()->id,
                    // Comment date slightly after ticket creation
                    'created_at' => fake()->dateTimeBetween($ticket->created_at, Carbon::parse($ticket->created_at)->addHours(12)),
                ]);

                // Add reactions
                if (fake()->boolean(60)) {
                    $reactors = $users->random(fake()->numberBetween(1, 3));
                    foreach ($reactors as $reactor) {
                        TicketCommentReaction::create([
                            'comment_id' => $comment->id,
                            'user_id' => $reactor->id,
                            'emoji' => fake()->randomElement(['👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅', '👀', '💯']),
                        ]);
                    }
                }

                // Add votes
                if (fake()->boolean(60)) {
                    $voters = $users->random(fake()->numberBetween(1, 5));
                    foreach ($voters as $voter) {
                        TicketCommentVote::create([
                            'comment_id' => $comment->id,
                            'user_id' => $voter->id,
                            'type' => fake()->randomElement(['up', 'down']),
                        ]);
                    }
                }
            }
        });

        $this->command->info('Created 200 tickets for March 23 and March 25 with comments, reactions, and votes.');
    }
}
