<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Notification;

class TicketCreated extends Notification
{
    public function __construct(public readonly Ticket $ticket, public readonly string $creatorName) {}

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
            'type' => 'ticket_created',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'message' => "New ticket created by {$this->creatorName}: TKT-".(1000 + $this->ticket->id)." - {$this->ticket->title}",
        ];
    }
}
