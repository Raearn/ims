<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::observe(UserObserver::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Normalize role for comparisons, middleware, and Inertia (handles legacy casing / whitespace in DB).
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                return strtolower(trim($value));
            },
        );
    }

    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function handledTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_handlers');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isTechnical(): bool
    {
        return $this->role === 'technical';
    }

    public function canModerateComments(): bool
    {
        return $this->isAdmin() || $this->isSupervisor();
    }

    /**
     * Whether the user may use ticket-scoped thread APIs (comments, history, subscription, etc.).
     */
    public function canAccessTicketThread(Ticket $ticket): bool
    {
        if ($this->isAdmin() || $this->isSupervisor()) {
            return true;
        }

        return (int) $ticket->user_id === (int) $this->id
            || $ticket->handlers()->whereKey($this->id)->exists();
    }

    /**
     * Tickets this user reported or is assigned to handle (helpdesk scope for technical users).
     *
     * @return Builder<Ticket>
     */
    public function visibleHelpdeskTicketsQuery(): Builder
    {
        return Ticket::query()->where(function (Builder $q): void {
            $q->where('user_id', $this->id)
                ->orWhereHas('handlers', fn (Builder $h) => $h->where('users.id', $this->id));
        });
    }

    /**
     * Relative URL to send the user after login, registration, or similar when no valid "intended" URL exists.
     */
    public function defaultAuthenticatedRedirectUrl(): string
    {
        if ($this->isAdmin() || $this->isSupervisor()) {
            return route('dashboard', absolute: false);
        }

        if ($this->isTechnical()) {
            return route('home', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }

    /**
     * Relative URL after email verification (appends query flag for the frontend).
     */
    public function emailVerifiedRedirectUrl(): string
    {
        $base = $this->defaultAuthenticatedRedirectUrl();
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'verified=1';
    }
}
