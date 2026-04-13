<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Support\IncidentInertiaPayload;
use Inertia\Inertia;
use Inertia\Response;

class MyIncidentsController extends Controller
{
    /**
     * Technical helpdesk: incidents the user reported or is assigned to handle.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $categoryDisplayPaths = TicketCategory::displayPathLookupFromSettings();

        $tickets = $user->visibleHelpdeskTicketsQuery()
            ->with(['handlers', 'reporter', 'tags'])
            ->withCount('comments')
            ->latest()
            ->get();

        return Inertia::render('MyIncidents', [
            'tickets' => $tickets->map(fn (Ticket $ticket) => IncidentInertiaPayload::listRow($ticket, $categoryDisplayPaths)),
            'categories' => TicketCategory::orderedTreeForSettings()->map(fn (TicketCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'parent_id' => $c->parent_id,
            ])->values()->all(),
            'priorities' => TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']),
        ]);
    }
}
