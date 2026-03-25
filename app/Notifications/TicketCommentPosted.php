<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Notifications\Notification;

class TicketCommentPosted extends Notification
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketComment $comment,
        public readonly string $commenterName,
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
        $tktId = 'TKT-'.(1000 + $this->ticket->id);

        return [
            'type' => 'ticket_comment_posted',
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'comment_id' => $this->comment->id,
            'message' => "{$this->commenterName} commented on {$tktId}: {$this->ticket->title}",
        ];
    }
}
