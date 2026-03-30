<?php

namespace Database\Seeders;

use App\Models\Tag;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\TicketCommentVote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']
        );

        $supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor@example.com'],
            ['name' => 'Supervisor User', 'password' => bcrypt('password'), 'role' => 'technical']
        );

        if (User::count() < 10) {
            User::factory(10)->create(); // Default technical users
        }

        // Create some tickets
        $users = User::all();

        $startDate = Carbon::create(2026, 3, 23);
        $endDate = Carbon::create(2026, 3, 29, 23, 59, 59);

        // Delete existing data to simulate a "fresh" start without actually dropping tables
        TicketActivity::query()->delete();
        TicketCommentVote::query()->delete();
        TicketCommentReaction::query()->delete();
        TicketComment::query()->delete();
        DB::table('ticket_handlers')->delete();
        Ticket::query()->delete();

        $this->call([
            TagSeeder::class,
            TicketStatusSeeder::class,
            TicketCategorySeeder::class,
            TicketPrioritySeeder::class,
            SettingSeeder::class,
            TicketConfigSeeder::class,
        ]);

        $tags = Tag::pluck('id');

        Ticket::factory(300)->make([
            'user_id' => fn () => $users->random()->id,
            'created_at' => fn () => fake()->dateTimeBetween($startDate, $endDate),
        ])->each(function (Ticket $ticket) use ($users, $tags) {
            $ticket->updated_at = clone $ticket->created_at;
            $ticket->save();

            // All of them should have handlers except for ticket with open status
            if ($ticket->status !== 'Open') {
                $ticket->handlers()->attach($users->random()->id);
            }

            if ($tags->count() > 0) {
                $ticket->tags()->attach($tags->random(rand(1, 3)));
            }

            // Create comments
            $numComments = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $numComments; $i++) {
                $comment = TicketComment::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $users->random()->id,
                    'created_at' => fake()->dateTimeBetween($ticket->created_at, Carbon::parse($ticket->created_at)->addDays(2)),
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

        $this->call([
            TicketActivitySeeder::class,
        ]);
    }
}
