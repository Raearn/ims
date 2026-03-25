<?php

namespace App\Observers;

use App\Models\TicketActivity;
use App\Models\TicketComment;

class TicketCommentObserver
{
    public function created(TicketComment $comment): void
    {
        TicketActivity::create([
            'ticket_id' => $comment->ticket_id,
            'user_id' => auth()->id(),
            'action' => 'comment_posted',
            'old_value' => null,
            'new_value' => $this->snippet($comment->body),
            'created_at' => now(),
        ]);
    }

    public function deleted(TicketComment $comment): void
    {
        TicketActivity::create([
            'ticket_id' => $comment->ticket_id,
            'user_id' => auth()->id(),
            'action' => 'comment_deleted',
            'old_value' => $this->snippet($comment->body),
            'new_value' => null,
            'created_at' => now(),
        ]);
    }

    public function updated(TicketComment $comment): void
    {
        if (! $comment->isDirty('is_pinned')) {
            return;
        }

        TicketActivity::create([
            'ticket_id' => $comment->ticket_id,
            'user_id' => auth()->id(),
            'action' => $comment->is_pinned ? 'comment_pinned' : 'comment_unpinned',
            'old_value' => null,
            'new_value' => $this->snippet($comment->body),
            'created_at' => now(),
        ]);
    }

    private function snippet(string $body): string
    {
        return mb_substr(strip_tags($body), 0, 80);
    }
}
