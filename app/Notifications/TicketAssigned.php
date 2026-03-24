<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket) {}

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
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'message' => 'You have been assigned to ticket TKT-'.(1000 + $this->ticket->id).": {$this->ticket->title}",
        ];
    }
}
