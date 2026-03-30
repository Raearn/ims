<?php

namespace App\Observers;

use App\Models\TicketActivity;
use App\Models\User;

class UserObserver
{
    public function updating(User $user): void
    {
        if (! auth()->check()) {
            return;
        }

        $actorId = auth()->id();

        if ($user->isDirty('two_factor_confirmed_at')) {
            $wasConfirmed = $user->getOriginal('two_factor_confirmed_at') !== null;
            $isConfirmed = $user->two_factor_confirmed_at !== null;

            if ($isConfirmed && ! $wasConfirmed) {
                TicketActivity::create([
                    'ticket_id' => null,
                    'user_id' => $actorId,
                    'action' => 'two_factor_enabled',
                    'old_value' => null,
                    'new_value' => json_encode([
                        'subject_user_id' => $user->id,
                        'email' => $user->email,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            } elseif (! $isConfirmed && $wasConfirmed) {
                TicketActivity::create([
                    'ticket_id' => null,
                    'user_id' => $actorId,
                    'action' => 'two_factor_disabled',
                    'old_value' => null,
                    'new_value' => json_encode([
                        'subject_user_id' => $user->id,
                        'email' => $user->email,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            }
        }

        if ($user->isDirty('two_factor_recovery_codes')
            && $user->two_factor_recovery_codes !== null
            && $user->two_factor_confirmed_at !== null
            && ! $user->isDirty('two_factor_confirmed_at')) {
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => $actorId,
                'action' => 'two_factor_recovery_codes_regenerated',
                'old_value' => null,
                'new_value' => json_encode([
                    'subject_user_id' => $user->id,
                    'email' => $user->email,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }
    }
}
