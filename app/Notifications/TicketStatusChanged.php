<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_status_changed',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => 'Ticket TKT-'.(1000 + $this->ticket->id)." status changed from {$this->oldStatus} to {$this->newStatus}.",
        ];
    }
}
