<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketActivity;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'old_value' => null,
            'new_value' => $ticket->title,
            'created_at' => now(),
        ]);
    }

    public function updating(Ticket $ticket): void
    {
        $userId = auth()->id();

        if ($ticket->isDirty('status')) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => 'status_changed',
                'old_value' => $ticket->getOriginal('status'),
                'new_value' => $ticket->status,
                'created_at' => now(),
            ]);
        }

        if ($ticket->isDirty('priority')) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => 'priority_changed',
                'old_value' => $ticket->getOriginal('priority'),
                'new_value' => $ticket->priority,
                'created_at' => now(),
            ]);
        }

        if ($ticket->isDirty('solution') && $ticket->solution !== null) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => 'solution_updated',
                'old_value' => $ticket->getOriginal('solution'),
                'new_value' => $ticket->solution,
                'created_at' => now(),
            ]);
        }

        $contentFields = ['title', 'description', 'category', 'attachment'];
        $dirtyContent = array_values(array_filter($contentFields, fn (string $f) => $ticket->isDirty($f)));
        if ($dirtyContent !== []) {
            $labels = array_map(fn (string $f) => match ($f) {
                'title' => 'Title',
                'description' => 'Description',
                'category' => 'Category',
                'attachment' => 'Attachment',
                default => $f,
            }, $dirtyContent);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => 'ticket_edited',
                'old_value' => null,
                'new_value' => 'Updated: '.implode(', ', $labels),
                'created_at' => now(),
            ]);
        }
    }
}
