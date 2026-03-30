<?php

namespace App\Providers;

use App\Models\TicketActivity;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'user_login',
                'old_value' => null,
                'new_value' => $event->user->name,
                'created_at' => now(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user) {
                return;
            }

            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'user_logout',
                'old_value' => null,
                'new_value' => $event->user->name,
                'created_at' => now(),
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            $request = $event->request;

            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => null,
                'action' => 'user_login_lockout',
                'old_value' => null,
                'new_value' => json_encode([
                    'email' => $request->string('email')->toString(),
                    'ip' => $request->ip(),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        });
    }
}
