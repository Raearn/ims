<?php

namespace App\Models;

use App\Observers\TicketObserver;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Statuses that clear {@see $resolved_at} when set (ticket reopened or not yet resolved).
     *
     * @var list<string>
     */
    public const STATUSES_CLEARING_RESOLVED_AT = ['Open', 'In Progress', 'On Hold'];

    protected $fillable = [
        'title',
        'description',
        'attachment',
        'status',
        'resolved_at',
        'solution',
        'priority',
        'category',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Whether moving to this status should clear {@see $resolved_at}.
     */
    public static function statusClearsResolvedAt(string $status): bool
    {
        return in_array($status, self::STATUSES_CLEARING_RESOLVED_AT, true);
    }

    /**
     * Automatically manage resolved_at when status changes.
     */
    protected static function booted(): void
    {
        static::saving(function (Ticket $ticket) {
            if ($ticket->isDirty('status')) {
                if ($ticket->status === 'Resolved') {
                    $ticket->resolved_at = now();
                } elseif (self::statusClearsResolvedAt($ticket->status)) {
                    $ticket->resolved_at = null;
                }
            }
        });

        static::observe(TicketObserver::class);
    }

    /**
     * Get the user who reported the ticket.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the handlers assigned to this ticket.
     */
    public function handlers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_handlers');
    }

    /**
     * Get the comments for this ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Get the users subscribed to comment notifications for this ticket.
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_subscriptions');
    }

    /**
     * Get the activity log entries for this ticket.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    /**
     * Get the tags associated with the ticket.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
