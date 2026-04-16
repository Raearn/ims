<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class IncidentInertiaPayload
{
    /**
     * @param  array<int, string>  $categoryDisplayPaths
     * @return array<string, mixed>
     */
    public static function listRow(Ticket $ticket, array $categoryDisplayPaths): array
    {
        return [
            'numericId' => $ticket->id,
            'id' => 'TKT-'.(1000 + $ticket->id),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => TicketCategory::displayLabelForTicket($ticket->ticket_category_id, $ticket->category, $categoryDisplayPaths),
            'ticketCategoryId' => $ticket->ticket_category_id,
            'tags' => $ticket->tags->pluck('name')->toArray(),
            'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
            'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'reporter' => $ticket->reporter?->name ?? 'Unknown',
            'reporterId' => $ticket->user_id,
            'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
            'createdAt' => $ticket->created_at->diffForHumans(),
            'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
            'createdAtRaw' => $ticket->created_at->format('Y-m-d'),
            'incidentOccurredAt' => $ticket->incident_occurred_at?->format('Y-m-d\TH:i'),
            'incidentOccurredAtFormatted' => $ticket->incident_occurred_at?->format('M d, Y \a\t h:i A'),
            'solution' => $ticket->solution,
            'resolvedInDuration' => $ticket->resolved_at
                ? $ticket->resolved_at->diffForHumans($ticket->incident_occurred_at ?? $ticket->created_at, CarbonInterface::DIFF_ABSOLUTE, false, 2)
                : null,
            'resolvedAtFormatted' => $ticket->resolved_at?->format('M d, Y \a\t h:i A'),
            'resolvedAtRaw' => $ticket->resolved_at?->format('Y-m-d\TH:i'),
            'commentsCount' => (int) ($ticket->comments_count ?? 0),
        ];
    }

    /**
     * @return array{ticket: array<string, mixed>, categories: list<array<string, mixed>>, priorities: Collection, statuses: Collection}
     */
    public static function detailPayload(Ticket $ticket): array
    {
        $ticket->loadMissing(['handlers', 'reporter', 'tags']);
        $ticket->loadCount('comments');

        $categoryDisplayPaths = TicketCategory::displayPathLookupFromSettings();

        return [
            'ticket' => [
                'numericId' => $ticket->id,
                'id' => 'TKT-'.(1000 + $ticket->id),
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => TicketCategory::displayLabelForTicket($ticket->ticket_category_id, $ticket->category, $categoryDisplayPaths),
                'ticketCategoryId' => $ticket->ticket_category_id,
                'tags' => $ticket->tags->pluck('name')->toArray(),
                'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
                'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'reporter' => $ticket->reporter?->name ?? 'Unknown',
                'reporterId' => $ticket->user_id,
                'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
                'createdAt' => $ticket->created_at->diffForHumans(),
                'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
                'createdAtRaw' => $ticket->created_at->format('Y-m-d'),
                'incidentOccurredAt' => $ticket->incident_occurred_at?->format('Y-m-d\TH:i'),
                'incidentOccurredAtFormatted' => $ticket->incident_occurred_at?->format('M d, Y \a\t h:i A'),
                'solution' => $ticket->solution,
                'resolvedInDuration' => $ticket->resolved_at
                    ? $ticket->resolved_at->diffForHumans($ticket->incident_occurred_at ?? $ticket->created_at, CarbonInterface::DIFF_ABSOLUTE, false, 2)
                    : null,
                'resolvedAtFormatted' => $ticket->resolved_at?->format('M d, Y \a\t h:i A'),
                'resolvedAtRaw' => $ticket->resolved_at?->format('Y-m-d\TH:i'),
                'commentsCount' => (int) $ticket->comments_count,
            ],
            'categories' => TicketCategory::orderedTreeForSettings()->map(fn (TicketCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'parent_id' => $c->parent_id,
            ])->values()->all(),
            'priorities' => TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']),
        ];
    }
}
