<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketCommentReaction;
use App\Models\TicketCommentVote;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCommentPosted;
use App\Notifications\TicketCreated;
use App\Notifications\TicketStatusChanged;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return redirect()->route('dashboard');
        } elseif ($user->isSupervisor()) {
            return redirect()->route('supervisor.dashboard');
        }

        return redirect()->route('dashboard'); // technical users or others can have a default too
    }

    return Inertia::render('Welcome');
})->name('home');

Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('dashboard/export-pdf', [DashboardController::class, 'exportPdf'])->name('dashboard.export-pdf');
    Route::post('tickets/export-excel-audit', function () {
        $validated = request()->validate([
            'ticket_count' => ['nullable', 'integer', 'min:0', 'max:500000'],
        ]);
        $count = $validated['ticket_count'] ?? null;
        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'tickets_export_excel',
            'old_value' => null,
            'new_value' => $count !== null
                ? "Exported {$count} ticket(s)"
                : 'Tickets list (Excel)',
            'created_at' => now(),
        ]);

        return response()->noContent();
    })->name('tickets.export-excel-audit');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tickets/by-priority/{priority}', function (string $priority) {
        $tickets = Ticket::with(['reporter', 'handlers', 'tags'])
            ->where('priority', $priority)
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'numericId' => $t->id,
                'tktId' => 'TKT-'.(1000 + $t->id),
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'category' => $t->category,
                'tags' => $t->tags->pluck('name')->toArray(),
                'reporter' => $t->reporter?->name ?? 'Unknown',
                'reporterId' => $t->user_id,
                'handlerIds' => $t->handlers->pluck('id')->toArray(),
                'handlers' => $t->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'attachmentUrl' => $t->attachment ? Storage::disk('public')->url($t->attachment) : null,
                'createdAtFormatted' => $t->created_at->format('M d, Y \a\t h:i A'),
                'time' => $t->created_at->diffForHumans(),
            ]);

        return response()->json($tickets);
    })->name('tickets.by-priority');

    Route::get('tickets/by-category/{category}', function (string $category) {
        $tickets = Ticket::with(['reporter', 'handlers', 'tags'])
            ->where('category', $category)
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'numericId' => $t->id,
                'tktId' => 'TKT-'.(1000 + $t->id),
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'category' => $t->category,
                'tags' => $t->tags->pluck('name')->toArray(),
                'reporter' => $t->reporter?->name ?? 'Unknown',
                'reporterId' => $t->user_id,
                'handlerIds' => $t->handlers->pluck('id')->toArray(),
                'handlers' => $t->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'attachmentUrl' => $t->attachment ? Storage::disk('public')->url($t->attachment) : null,
                'createdAtFormatted' => $t->created_at->format('M d, Y \a\t h:i A'),
                'time' => $t->created_at->diffForHumans(),
            ]);

        return response()->json($tickets);
    })->name('tickets.by-category');

    Route::get('tickets/by-tag', function () {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tag = Tag::where('name', $validated['name'])->first();
        if ($tag === null) {
            return response()->json([]);
        }

        $tickets = Ticket::with(['reporter', 'handlers', 'tags'])
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'numericId' => $t->id,
                'tktId' => 'TKT-'.(1000 + $t->id),
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority,
                'category' => $t->category,
                'tags' => $t->tags->pluck('name')->toArray(),
                'reporter' => $t->reporter?->name ?? 'Unknown',
                'reporterId' => $t->user_id,
                'handlerIds' => $t->handlers->pluck('id')->toArray(),
                'handlers' => $t->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'attachmentUrl' => $t->attachment ? Storage::disk('public')->url($t->attachment) : null,
                'createdAtFormatted' => $t->created_at->format('M d, Y \a\t h:i A'),
                'time' => $t->created_at->diffForHumans(),
            ]);

        return response()->json($tickets);
    })->name('tickets.by-tag');

    Route::get('tickets', function () {
        $tickets = Ticket::with(['handlers', 'reporter', 'tags'])
            ->withCount('comments')
            ->latest()
            ->get();

        return Inertia::render('Tickets', [
            'tickets' => $tickets->map(fn ($ticket) => [
                'numericId' => $ticket->id,
                'id' => 'TKT-'.(1000 + $ticket->id),
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'tags' => $ticket->tags->pluck('name')->toArray(),
                'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
                'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'reporter' => $ticket->reporter?->name ?? 'Unknown',
                'reporterId' => $ticket->user_id,
                'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
                'createdAt' => $ticket->created_at->diffForHumans(),
                'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
                'createdAtRaw' => $ticket->created_at->format('Y-m-d'),
                'solution' => $ticket->solution,
                'resolvedInDuration' => $ticket->resolved_at
                    ? $ticket->resolved_at->diffForHumans($ticket->created_at, CarbonInterface::DIFF_ABSOLUTE, false, 2)
                    : null,
                'resolvedAtFormatted' => $ticket->resolved_at?->format('M d, Y \a\t h:i A'),
                'commentsCount' => $ticket->comments_count,
            ]),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'categories' => TicketCategory::orderBy('sort_order')->get(['id', 'name', 'icon']),
            'priorities' => TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']),
            'allTags' => Tag::pluck('name')->toArray(),
        ]);
    })->name('tickets');

    Route::get('tickets/{ticket}/detail', function (Ticket $ticket) {
        $ticket->load(['handlers', 'reporter', 'tags']);
        $ticket->loadCount('comments');

        return response()->json([
            'ticket' => [
                'numericId' => $ticket->id,
                'id' => 'TKT-'.(1000 + $ticket->id),
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'tags' => $ticket->tags->pluck('name')->toArray(),
                'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
                'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'reporter' => $ticket->reporter?->name ?? 'Unknown',
                'reporterId' => $ticket->user_id,
                'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
                'createdAt' => $ticket->created_at->diffForHumans(),
                'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
                'createdAtRaw' => $ticket->created_at->format('Y-m-d'),
                'solution' => $ticket->solution,
                'resolvedInDuration' => $ticket->resolved_at
                    ? $ticket->resolved_at->diffForHumans($ticket->created_at, CarbonInterface::DIFF_ABSOLUTE, false, 2)
                    : null,
                'resolvedAtFormatted' => $ticket->resolved_at?->format('M d, Y \a\t h:i A'),
                'commentsCount' => $ticket->comments_count,
            ],
            'priorities' => TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']),
        ]);
    })->name('tickets.detail-json');

    Route::post('tickets', function () {
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['required', 'string', Rule::in(TicketCategory::pluck('name')->toArray())],
            'priority' => ['required', 'string', Rule::in(TicketPriority::pluck('name')->toArray())],
            'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
            'handler_ids' => [
                Rule::requiredIf(fn () => in_array(request('status'), TicketStatus::namesRequiringHandlersForForms(), true)),
                'nullable',
                'array',
            ],
            'handler_ids.*' => ['exists:users,id'],
            'solution' => [
                'nullable',
                'string',
            ],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string'],
            'attachment' => 'nullable|image|max:4096',
        ]);

        if (in_array($validated['status'], TicketStatus::namesRequiringHandlersForForms(), true)) {
            $handlerIds = $validated['handler_ids'] ?? [];
            if (! is_array($handlerIds) || count($handlerIds) < 1) {
                throw ValidationException::withMessages([
                    'handler_ids' => 'At least one handler is required for this status.',
                ]);
            }
        }

        if (request()->hasFile('attachment')) {
            $validated['attachment'] = request()->file('attachment')->store('attachments', 'public');
        }

        $ticket = Ticket::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'attachment' => $validated['attachment'] ?? null,
            'status' => $validated['status'],
            'solution' => $validated['solution'] ?? null,
            'priority' => $validated['priority'],
            'category' => $validated['category'],
            'user_id' => auth()->id(),
        ]);

        $tagIds = [];
        if (! empty($validated['tags'])) {
            foreach ($validated['tags'] as $tagName) {
                $tagIds[] = Tag::firstOrCreate(['name' => $tagName])->id;
            }
        }
        $ticket->tags()->sync($tagIds);

        $handlerIds = $validated['handler_ids'] ?? [];
        $ticket->handlers()->sync($handlerIds);

        if (! empty($handlerIds)) {
            User::whereIn('id', $handlerIds)->each(
                fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
            );

            $handlerNames = User::whereIn('id', $handlerIds)->pluck('name')->implode(', ');
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'handler_assigned',
                'old_value' => null,
                'new_value' => $handlerNames,
                'created_at' => now(),
            ]);
        }

        $admins = User::where('role', 'admin')
            ->where('id', '!=', auth()->id())
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new TicketCreated($ticket, auth()->user()->name));
        }

        return redirect()->back()->with('success', 'Ticket created successfully.');
    })->name('tickets.store');

    Route::patch('tickets/bulk/status', function () {
        $validated = request()->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['exists:tickets,id'],
            'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
            'handler_ids' => ['nullable', 'array'],
            'handler_ids.*' => ['exists:users,id'],
            'solution' => [
                'nullable',
                'string',
            ],
        ]);

        if (in_array($validated['status'], TicketStatus::namesRequiringHandlersForForms(), true)) {
            $handlerIds = $validated['handler_ids'] ?? [];
            if (! is_array($handlerIds) || count($handlerIds) < 1) {
                throw ValidationException::withMessages([
                    'handler_ids' => 'At least one handler is required for this status.',
                ]);
            }
        }

        $newStatus = $validated['status'];
        $tickets = Ticket::with(['reporter', 'handlers'])->whereIn('id', $validated['ticket_ids'])->get();

        // Capture old statuses before bulk update
        $oldStatuses = $tickets->pluck('status', 'id');

        $bulkPayload = [
            'status' => $newStatus,
        ];
        if ($newStatus === 'Resolved') {
            $bulkPayload['resolved_at'] = now();
            $bulkPayload['solution'] = $validated['solution'] ?? null;
        } elseif (Ticket::statusClearsResolvedAt($newStatus)) {
            $bulkPayload['resolved_at'] = null;
            $bulkPayload['solution'] = null;
        }

        Ticket::whereIn('id', $validated['ticket_ids'])->update($bulkPayload);

        // Log status changes manually (mass update bypasses the model observer)
        $solution = $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : null;
        $now = now();
        foreach ($tickets as $ticket) {
            $oldStatus = $oldStatuses->get($ticket->id);
            if ($oldStatus !== $newStatus) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'status_changed',
                    'old_value' => $oldStatus,
                    'new_value' => $newStatus,
                    'created_at' => $now,
                ]);
            }
            if ($solution) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'solution_updated',
                    'old_value' => null,
                    'new_value' => $solution,
                    'created_at' => $now,
                ]);
            }
        }

        // Sync handlers on every affected ticket:
        // - No-handler statuses (e.g. queue) → clear handlers
        // - Other statuses → sync provided handler_ids (may be empty when optional)
        $handlerIds = in_array($newStatus, TicketStatus::namesWithNoHandlersInUi(), true)
            ? []
            : ($validated['handler_ids'] ?? null);
        if (! is_null($handlerIds)) {
            foreach ($tickets as $ticket) {
                $existingIds = $ticket->handlers->pluck('id')->toArray();
                $ticket->handlers()->sync($handlerIds);

                $addedIds = array_diff($handlerIds, $existingIds);
                $removedIds = array_diff($existingIds, $handlerIds);

                if (! empty($addedIds)) {
                    TicketActivity::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => auth()->id(),
                        'action' => 'handler_assigned',
                        'old_value' => null,
                        'new_value' => User::whereIn('id', $addedIds)->pluck('name')->implode(', '),
                        'created_at' => $now,
                    ]);
                }

                if (! empty($removedIds)) {
                    TicketActivity::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => auth()->id(),
                        'action' => 'handler_removed',
                        'old_value' => User::whereIn('id', $removedIds)->pluck('name')->implode(', '),
                        'new_value' => null,
                        'created_at' => $now,
                    ]);
                }
            }
        }

        // Fire notifications
        foreach ($tickets as $ticket) {
            $oldStatus = $oldStatuses->get($ticket->id);
            if ($oldStatus !== $newStatus) {
                $ticket->refresh();
                $notifiables = User::where('role', 'admin')->get();
                if ($ticket->reporter) {
                    $notifiables->push($ticket->reporter);
                }
                $notifiables->unique('id')->each(function ($notifiable) use ($ticket, $oldStatus, $newStatus) {
                    $notifiable->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
                });
            }
        }

        if (! empty($handlerIds)) {
            User::whereIn('id', $handlerIds)->each(function (User $handler) use ($tickets) {
                foreach ($tickets as $ticket) {
                    $handler->notify(new TicketAssigned($ticket));
                }
            });
        }

        return redirect()->back()->with('success', count($validated['ticket_ids']).' ticket(s) updated.');
    })->name('tickets.bulk.status');

    Route::patch('tickets/bulk/handlers', function () {
        $validated = request()->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['exists:tickets,id'],
            'handler_ids' => ['required', 'array', 'min:1'],
            'handler_ids.*' => ['exists:users,id'],
        ]);

        $tickets = Ticket::with('handlers')->whereIn('id', $validated['ticket_ids'])->get();
        $now = now();

        $tickets->each(function (Ticket $ticket) use ($validated, $now) {
            $existingHandlerIds = $ticket->handlers->pluck('id')->toArray();
            $ticket->handlers()->sync($validated['handler_ids']);

            $addedIds = array_diff($validated['handler_ids'], $existingHandlerIds);
            $removedIds = array_diff($existingHandlerIds, $validated['handler_ids']);

            if (! empty($addedIds)) {
                User::whereIn('id', $addedIds)->each(
                    fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
                );
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'handler_assigned',
                    'old_value' => null,
                    'new_value' => User::whereIn('id', $addedIds)->pluck('name')->implode(', '),
                    'created_at' => $now,
                ]);
            }

            if (! empty($removedIds)) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'handler_removed',
                    'old_value' => User::whereIn('id', $removedIds)->pluck('name')->implode(', '),
                    'new_value' => null,
                    'created_at' => $now,
                ]);
            }
        });

        return redirect()->back()->with('success', count($validated['ticket_ids']).' ticket(s) updated.');
    })->name('tickets.bulk.handlers');

    Route::delete('tickets/bulk', function () {
        $validated = request()->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['exists:tickets,id'],
        ]);

        // Capture titles before cascade wipes the rows
        $tickets = Ticket::whereIn('id', $validated['ticket_ids'])->get(['id', 'title']);

        $tickets->each(fn (Ticket $t) => TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'ticket_deleted',
            'old_value' => 'TKT-'.(1000 + $t->id).': '.$t->title,
            'new_value' => null,
            'created_at' => now(),
        ]));

        Ticket::whereIn('id', $validated['ticket_ids'])->delete();

        return redirect()->back()->with('success', count($validated['ticket_ids']).' ticket(s) deleted.');
    })->name('tickets.bulk.destroy');

    Route::delete('tickets/{ticket}', function (Ticket $ticket) {
        if ($ticket->attachment) {
            Storage::disk('public')->delete($ticket->attachment);
        }

        // Log before cascade-delete removes the row
        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'ticket_deleted',
            'old_value' => 'TKT-'.(1000 + $ticket->id).': '.$ticket->title,
            'new_value' => null,
            'created_at' => now(),
        ]);

        $ticket->handlers()->detach();
        $ticket->delete();

        return redirect()->back()->with('success', 'Ticket deleted successfully.');
    })->name('tickets.destroy');

    Route::patch('tickets/{ticket}/status', function (Ticket $ticket) {
        $validated = request()->validate([
            'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
            'handler_ids' => ['nullable', 'array'],
            'handler_ids.*' => ['exists:users,id'],
            'solution' => [
                'nullable',
                'string',
            ],
        ]);

        $oldStatus = $ticket->status;
        $newStatus = $validated['status'];

        $ticket->update([
            'status' => $newStatus,
            'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : $ticket->solution,
        ]);

        $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();

        if (in_array($newStatus, TicketStatus::namesWithNoHandlersInUi(), true)) {
            $ticket->handlers()->sync([]);
            $removedIds = $existingHandlerIds;
            if (! empty($removedIds)) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'handler_removed',
                    'old_value' => User::whereIn('id', $removedIds)->pluck('name')->implode(', '),
                    'new_value' => null,
                    'created_at' => now(),
                ]);
            }
        } elseif (! empty($validated['handler_ids'])) {
            $ticket->handlers()->sync($validated['handler_ids']);

            $addedIds = array_diff($validated['handler_ids'], $existingHandlerIds);
            $removedIds = array_diff($existingHandlerIds, $validated['handler_ids']);

            if (! empty($addedIds)) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'handler_assigned',
                    'old_value' => null,
                    'new_value' => User::whereIn('id', $addedIds)->pluck('name')->implode(', '),
                    'created_at' => now(),
                ]);
            }

            if (! empty($removedIds)) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'action' => 'handler_removed',
                    'old_value' => User::whereIn('id', $removedIds)->pluck('name')->implode(', '),
                    'new_value' => null,
                    'created_at' => now(),
                ]);
            }
        }

        if ($oldStatus !== $newStatus) {
            $ticket->refresh();
            $notifiables = User::where('role', 'admin')->get();
            if ($ticket->reporter) {
                $notifiables->push($ticket->reporter);
            }
            $notifiables->unique('id')->each(function ($notifiable) use ($ticket, $oldStatus, $newStatus) {
                $notifiable->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
            });
        }

        return redirect()->back()->with('success', 'Ticket status updated.');
    })->name('tickets.status.update');

    Route::patch('tickets/{ticket}/handlers', function (Ticket $ticket) {
        $validated = request()->validate([
            'handler_ids' => ['required', 'array', 'min:1'],
            'handler_ids.*' => ['exists:users,id'],
            'status' => ['nullable', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
            'solution' => [
                'nullable',
                'string',
            ],
        ]);

        if (in_array($ticket->status, TicketStatus::namesWithNoHandlersInUi(), true) && empty($validated['status'])) {
            abort(422, 'A new status is required when assigning handlers from a status that does not use handlers.');
        }

        $oldStatus = $ticket->status;
        $newStatus = $validated['status'] ?? null;

        if (! empty($newStatus)) {
            $ticket->update([
                'status' => $newStatus,
                'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : $ticket->solution,
            ]);
        }

        $ticket->refresh();
        $finalStatus = $ticket->status;

        $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();

        $targetHandlerIds = in_array($finalStatus, TicketStatus::namesWithNoHandlersInUi(), true)
            ? []
            : $validated['handler_ids'];

        $ticket->handlers()->sync($targetHandlerIds);

        // Notify only newly added handlers
        $newHandlerIds = array_diff($targetHandlerIds, $existingHandlerIds);
        if (! empty($newHandlerIds)) {
            User::whereIn('id', $newHandlerIds)->each(
                fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
            );
        }

        // Log handler assignment changes
        $addedIds = array_diff($targetHandlerIds, $existingHandlerIds);
        $removedIds = array_diff($existingHandlerIds, $targetHandlerIds);

        if (! empty($addedIds)) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'handler_assigned',
                'old_value' => null,
                'new_value' => User::whereIn('id', $addedIds)->pluck('name')->implode(', '),
                'created_at' => now(),
            ]);
        }

        if (! empty($removedIds)) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'handler_removed',
                'old_value' => User::whereIn('id', $removedIds)->pluck('name')->implode(', '),
                'new_value' => null,
                'created_at' => now(),
            ]);
        }

        if ($newStatus && $oldStatus !== $newStatus) {
            $ticket->refresh();
            $notifiables = User::where('role', 'admin')->get();
            if ($ticket->reporter) {
                $notifiables->push($ticket->reporter);
            }
            $notifiables->unique('id')->each(function ($notifiable) use ($ticket, $oldStatus, $newStatus) {
                $notifiable->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
            });
        }

        return redirect()->back()->with('success', 'Handlers updated successfully.');
    })->name('tickets.handlers.update');

    Route::put('tickets/{ticket}', function (Ticket $ticket) {
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['required', 'string', Rule::in(TicketCategory::pluck('name')->toArray())],
            'priority' => ['required', 'string', Rule::in(TicketPriority::pluck('name')->toArray())],
            'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
            'handler_ids' => [
                Rule::requiredIf(fn () => in_array(request('status'), TicketStatus::namesRequiringHandlersForForms(), true)),
                'nullable',
                'array',
            ],
            'handler_ids.*' => ['exists:users,id'],
            'solution' => [
                'nullable',
                'string',
            ],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string'],
            'attachment' => 'nullable|image|max:4096',
        ]);

        if (in_array($validated['status'], TicketStatus::namesRequiringHandlersForForms(), true)) {
            $handlerIds = $validated['handler_ids'] ?? [];
            if (! is_array($handlerIds) || count($handlerIds) < 1) {
                throw ValidationException::withMessages([
                    'handler_ids' => 'At least one handler is required for this status.',
                ]);
            }
        }

        if (request()->hasFile('attachment')) {
            if ($ticket->attachment) {
                Storage::disk('public')->delete($ticket->attachment);
            }
            $validated['attachment'] = request()->file('attachment')->store('attachments', 'public');
        }

        $oldStatus = $ticket->status;
        $newStatus = $validated['status'];
        $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();

        $normalizeTagList = static function (array $names): array {
            return collect($names)
                ->map(fn (mixed $n) => is_string($n) ? trim($n) : (string) $n)
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        };

        $oldTagNames = $normalizeTagList($ticket->tags()->pluck('name')->all());

        $ticket->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $newStatus,
            'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : $ticket->solution,
            'priority' => $validated['priority'],
            'category' => $validated['category'],
            'attachment' => $validated['attachment'] ?? $ticket->attachment,
        ]);

        $tagIds = [];
        if (! empty($validated['tags'])) {
            foreach ($validated['tags'] as $tagName) {
                $tagIds[] = Tag::firstOrCreate(['name' => $tagName])->id;
            }
        }
        $ticket->tags()->sync($tagIds);

        $newTagNames = $normalizeTagList($ticket->tags()->pluck('name')->all());
        if ($oldTagNames !== $newTagNames) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'ticket_edited',
                'old_value' => $oldTagNames === [] ? '—' : implode(', ', $oldTagNames),
                'new_value' => $newTagNames === [] ? '—' : implode(', ', $newTagNames),
                'created_at' => now(),
            ]);
        }

        $newHandlerIds = $validated['handler_ids'] ?? [];
        if (in_array($newStatus, TicketStatus::namesWithNoHandlersInUi(), true)) {
            $newHandlerIds = [];
        }

        $ticket->handlers()->sync($newHandlerIds);

        // Notify newly added handlers
        $addedHandlerIds = array_diff($newHandlerIds, $existingHandlerIds);
        if (! empty($addedHandlerIds)) {
            User::whereIn('id', $addedHandlerIds)->each(
                fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
            );
        }

        // Log handler assignment changes
        $removedHandlerIds = array_diff($existingHandlerIds, $newHandlerIds);

        if (! empty($addedHandlerIds)) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'handler_assigned',
                'old_value' => null,
                'new_value' => User::whereIn('id', $addedHandlerIds)->pluck('name')->implode(', '),
                'created_at' => now(),
            ]);
        }

        if (! empty($removedHandlerIds)) {
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'handler_removed',
                'old_value' => User::whereIn('id', $removedHandlerIds)->pluck('name')->implode(', '),
                'new_value' => null,
                'created_at' => now(),
            ]);
        }

        // Notify reporter and admins of status change
        if ($oldStatus !== $newStatus) {
            $ticket->refresh();
            $notifiables = User::where('role', 'admin')->get();
            if ($ticket->reporter) {
                $notifiables->push($ticket->reporter);
            }
            $notifiables->unique('id')->each(function ($notifiable) use ($ticket, $oldStatus, $newStatus) {
                $notifiable->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
            });
        }

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    })->name('tickets.update');

    // ── Users management ──────────────────────────────────────────────────────
    Route::get('users', function () {
        $mapTicket = fn ($t) => [
            'id' => $t->id,
            'tktId' => 'TKT-'.(1000 + $t->id),
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->category,
            'createdAt' => $t->created_at->diffForHumans(),
        ];

        $users = User::withCount(['reportedTickets', 'handledTickets'])
            ->with(['reportedTickets', 'handledTickets'])
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'createdAt' => $user->created_at->format('M d, Y'),
                'ticketsReported' => $user->reported_tickets_count,
                'ticketsHandled' => $user->handled_tickets_count,
                'reportedTickets' => $user->reportedTickets->map($mapTicket)->values()->toArray(),
                'handledTickets' => $user->handledTickets->map($mapTicket)->values()->toArray(),
            ]);

        return Inertia::render('Users', [
            'users' => $users,
            'currentUserId' => auth()->id(),
        ]);
    })->name('users');

    Route::post('users', function () {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:admin,supervisor,technical',
        ]);

        $user = User::create($validated);

        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'user_created',
            'old_value' => null,
            'new_value' => "{$user->name} ({$user->role})",
            'created_at' => now(),
        ]);

        return redirect()->back()->with('success', 'User created successfully.');
    })->name('users.store');

    Route::patch('users/{user}', function (User $user) {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string|in:admin,supervisor,technical',
        ]);

        $beforeName = $user->name;
        $beforeEmail = $user->email;
        $beforeRole = $user->role;

        $changes = [];
        if ($user->name !== $validated['name']) {
            $changes[] = "name: {$user->name} → {$validated['name']}";
        }
        if ($user->email !== $validated['email']) {
            $changes[] = "email: {$user->email} → {$validated['email']}";
        }

        $roleChanged = false;
        if ($user->id !== auth()->id() && $beforeRole !== $validated['role']) {
            $roleChanged = true;
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Prevent admins from changing their own role
        if ($user->id !== auth()->id()) {
            $user->role = $validated['role'];
        }

        if (! empty($validated['password'])) {
            $changes[] = 'password changed';
            $user->password = $validated['password'];
        }

        $user->save();

        if ($roleChanged) {
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'user_role_changed',
                'old_value' => "{$beforeName} ({$beforeEmail}) — {$beforeRole}",
                'new_value' => "{$validated['name']} ({$validated['email']}) — {$validated['role']}",
                'created_at' => now(),
            ]);
        }

        if ($changes !== []) {
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'user_updated',
                'old_value' => $beforeName,
                'new_value' => implode('; ', $changes),
                'created_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    })->name('users.update');
    Route::delete('users/{user}', function (User $user) {
        if ($user->id === auth()->id()) {
            abort(403, 'You cannot delete your own account.');
        }

        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'user_deleted',
            'old_value' => "{$user->name} ({$user->role})",
            'new_value' => null,
            'created_at' => now(),
        ]);

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    })->name('users.destroy');
});

Route::get('tickets/{ticket}/history', function (Ticket $ticket) {
    $activities = $ticket->activities()
        ->with('user:id,name')
        ->latest('created_at')
        ->get()
        ->map(fn ($a) => [
            'id' => $a->id,
            'action' => $a->action,
            'oldValue' => $a->old_value,
            'newValue' => $a->new_value,
            'userName' => $a->user?->name ?? 'System',
            'createdAt' => $a->created_at->diffForHumans(),
            'createdAtFormatted' => $a->created_at->format('M d, Y \a\t h:i A'),
        ]);

    return response()->json($activities);
})->middleware(['auth', 'verified'])->name('tickets.history');

// ──────────────────────────────────────────────────────────────────────────

Route::get('supervisor/dashboard', function () {
    return Inertia::render('SupervisorDashboard');
})->middleware(['auth', 'verified', 'role:supervisor'])->name('supervisor.dashboard');

// ── Notifications ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('notifications', function () {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json($notifications);
    })->name('notifications.index');

    Route::post('notifications/{id}/read', function (string $id) {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    })->name('notifications.read');

    Route::post('notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    })->name('notifications.read-all');
});

// ── Ticket Comments ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    $allowedEmojis = ['👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅', '👀', '💯'];

    // List comments for a ticket (also returns subscription state)
    Route::get('tickets/{ticket}/comments', function (Ticket $ticket) {
        $userId = auth()->id();

        $subscribed = $ticket->subscribers()->where('user_id', $userId)->exists();

        $comments = $ticket->comments()
            ->with(['user', 'reactions.user', 'votes.user'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($comment) use ($userId) {
                $grouped = $comment->reactions
                    ->groupBy('emoji')
                    ->map(fn ($group, $emoji) => [
                        'emoji' => $emoji,
                        'count' => $group->count(),
                        'reacted' => $group->contains('user_id', $userId),
                        'users' => $group->map(fn ($r) => $r->user?->name ?? 'Unknown')->values()->all(),
                    ])
                    ->values();

                $name = $comment->user->name ?? 'Unknown';
                $initials = collect(explode(' ', $name))
                    ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
                    ->take(2)
                    ->implode('');

                return [
                    'id' => $comment->id,
                    'userId' => $comment->user_id,
                    'userName' => $name,
                    'userInitials' => $initials,
                    'userRole' => $comment->user->role ?? 'user',
                    'userEmail' => $comment->user->email ?? '',
                    'body' => $comment->body,
                    'createdAt' => $comment->created_at->diffForHumans(),
                    'reactions' => $grouped,
                    'parentId' => $comment->parent_id,
                    'isPinned' => (bool) $comment->is_pinned,
                    'upvotes' => $comment->votes->where('type', 'up')->count(),
                    'downvotes' => $comment->votes->where('type', 'down')->count(),
                    'upvoters' => $comment->votes->where('type', 'up')->map(fn ($v) => $v->user?->name ?? 'Unknown')->values()->all(),
                    'downvoters' => $comment->votes->where('type', 'down')->map(fn ($v) => $v->user?->name ?? 'Unknown')->values()->all(),
                    'userVote' => $comment->votes->where('user_id', $userId)->first()?->type ?? null,
                ];
            });

        return response()->json([
            'subscribed' => $subscribed,
            'comments' => $comments,
        ]);
    })->name('tickets.comments.index');

    // Create a comment — auto-subscribes the commenter, then notifies all other subscribers
    Route::post('tickets/{ticket}/comments', function (Ticket $ticket) {
        $validated = request()->validate([
            'body' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', 'exists:ticket_comments,id'],
        ]);

        $user = auth()->user();
        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        // Auto-subscribe the commenter so they hear about future replies
        $ticket->subscribers()->syncWithoutDetaching([$user->id]);

        // Notify all subscribers and all admins, except the commenter
        $subscribers = $ticket->subscribers()->get();
        $admins = User::where('role', 'admin')->get();

        $subscribers->merge($admins)
            ->unique('id')
            ->reject(fn ($notifiable) => $notifiable->id === $user->id)
            ->each(fn ($notifiable) => $notifiable->notify(
                new TicketCommentPosted($ticket, $comment, $user->name)
            ));

        $comment->load('user', 'reactions');
        $name = $comment->user->name ?? 'Unknown';
        $initials = collect(explode(' ', $name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)
            ->implode('');

        return response()->json([
            'id' => $comment->id,
            'userId' => $comment->user_id,
            'userName' => $name,
            'userInitials' => $initials,
            'userRole' => $comment->user->role ?? 'user',
            'userEmail' => $comment->user->email ?? '',
            'body' => $comment->body,
            'createdAt' => $comment->created_at->diffForHumans(),
            'reactions' => [],
            'parentId' => $comment->parent_id,
            'isPinned' => false,
            'upvotes' => 0,
            'downvotes' => 0,
            'upvoters' => [],
            'downvoters' => [],
            'userVote' => null,
        ], 201);
    })->name('tickets.comments.store');

    // Toggle subscription to comment notifications for a ticket
    Route::post('tickets/{ticket}/subscribe', function (Ticket $ticket) {
        $userId = auth()->id();

        $exists = $ticket->subscribers()->where('user_id', $userId)->exists();

        if ($exists) {
            $ticket->subscribers()->detach($userId);
        } else {
            $ticket->subscribers()->attach($userId);
        }

        return response()->json(['subscribed' => ! $exists]);
    })->name('tickets.subscribe.toggle');

    // Delete a comment (own comment, or admin)
    Route::delete('ticket-comments/{comment}', function (TicketComment $comment) {
        $user = auth()->user();

        if ($comment->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'You can only delete your own comments.');
        }

        $comment->delete();

        return response()->json(['ok' => true]);
    })->name('ticket-comments.destroy');

    // Toggle an emoji reaction on a comment
    Route::post('ticket-comments/{comment}/reactions', function (TicketComment $comment) use ($allowedEmojis) {
        $validated = request()->validate([
            'emoji' => ['required', 'string', Rule::in($allowedEmojis)],
        ]);

        $userId = auth()->id();
        $emoji = $validated['emoji'];

        $existing = $comment->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $reacted = false;
        } else {
            $comment->reactions()->create([
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            $reacted = true;
        }

        TicketActivity::create([
            'ticket_id' => $comment->ticket_id,
            'user_id' => $userId,
            'action' => $reacted ? 'reaction_added' : 'reaction_removed',
            'old_value' => null,
            'new_value' => $emoji,
            'created_at' => now(),
        ]);

        $count = $comment->reactions()->where('emoji', $emoji)->count();
        $users = $comment->reactions()
            ->where('emoji', $emoji)
            ->with('user')
            ->get()
            ->map(fn ($r) => $r->user?->name ?? 'Unknown')
            ->values()
            ->all();

        return response()->json(['emoji' => $emoji, 'count' => $count, 'reacted' => $reacted, 'users' => $users]);
    })->name('ticket-comments.reactions.toggle');

    // Toggle upvote / downvote on a comment (mutually exclusive)
    Route::post('ticket-comments/{comment}/vote', function (TicketComment $comment) {
        $type = request()->validate(['type' => ['required', Rule::in(['up', 'down'])]])['type'];
        $userId = auth()->id();

        $existing = $comment->votes()->where('user_id', $userId)->first();
        $action = '';

        if ($existing?->type === $type) {
            $existing->delete();
            $action = $type === 'up' ? 'upvote_removed' : 'downvote_removed';
        } else {
            $comment->votes()->updateOrCreate(
                ['user_id' => $userId],
                ['type' => $type]
            );
            if ($existing) {
                $action = 'vote_changed';
            } else {
                $action = $type === 'up' ? 'upvote_added' : 'downvote_added';
            }
        }

        TicketActivity::create([
            'ticket_id' => $comment->ticket_id,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $existing?->type,
            'new_value' => str_ends_with($action, 'removed') ? null : $type,
            'created_at' => now(),
        ]);

        return response()->json([
            'upvotes' => $comment->votes()->where('type', 'up')->count(),
            'downvotes' => $comment->votes()->where('type', 'down')->count(),
            'upvoters' => $comment->votes()->where('type', 'up')->with('user')->get()->map(fn ($v) => $v->user?->name ?? 'Unknown')->values()->all(),
            'downvoters' => $comment->votes()->where('type', 'down')->with('user')->get()->map(fn ($v) => $v->user?->name ?? 'Unknown')->values()->all(),
            'userVote' => $comment->votes()->where('user_id', $userId)->value('type') ?? null,
        ]);
    })->name('ticket-comments.vote');

    // Toggle pin on a comment (admin or ticket reporter only)
    Route::post('ticket-comments/{comment}/pin', function (TicketComment $comment) {
        $user = auth()->user();
        $ticket = $comment->ticket;

        if (! ($user->isAdmin() || $ticket->user_id === $user->id)) {
            abort(403);
        }

        $comment->update(['is_pinned' => ! $comment->is_pinned]);

        return response()->json(['isPinned' => (bool) $comment->is_pinned]);
    })->name('ticket-comments.pin');

    // Upload an image for use inside a comment body
    Route::post('comments/images', function () {
        request()->validate([
            'image' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $path = request()->file('image')->store('comment-images', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    })->name('comments.images.store');
});

// ── Audit Log, Diagnostics, Settings (admin URL prefix) ─────────────────────
Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('audit-log', function () {
        $query = TicketActivity::with(['user:id,name,role', 'ticket:id,title'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($action = request('action')) {
            $query->where('action', $action);
        }
        if ($userId = request('user_id')) {
            $query->where('user_id', $userId);
        }
        if (request()->filled('ticket_id')) {
            $query->where('ticket_id', (int) request('ticket_id'));
        }
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_id', 'like', "%{$search}%")
                    ->orWhereHas('ticket', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%");
                    })
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('old_value', 'like', "%{$search}%")
                    ->orWhere('new_value', 'like', "%{$search}%");
            });
        }
        if ($from = request('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $activities = $query->paginate(50)->withQueryString();

        return Inertia::render('AuditLog', [
            'activities' => $activities->through(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'oldValue' => $a->old_value,
                'newValue' => $a->new_value,
                'userName' => $a->user?->name ?? 'System',
                'userRole' => $a->user?->role,
                'userId' => $a->user_id,
                'ticketId' => $a->ticket_id,
                'ticketTitle' => $a->ticket?->title ?? '—',
                'ticketTktId' => $a->ticket_id ? 'TKT-'.(1000 + $a->ticket_id) : '—',
                'createdAt' => $a->created_at->diffForHumans(),
                'createdAtFormatted' => $a->created_at->format('M d, Y \a\t h:i A'),
            ]),
            'filters' => request()->only(['action', 'user_id', 'search', 'from', 'to', 'ticket_id']),
            'users' => User::query()
                ->select('id', 'name', 'role')
                ->orderByRaw("case role when 'admin' then 1 when 'supervisor' then 2 when 'technical' then 3 else 4 end")
                ->orderBy('name')
                ->get(),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']),
        ]);
    })->name('audit-log');

    // ── Diagnostics ────────────────────────────────────────────────────────────
    Route::get('diagnostics', function () {
        // Collect database statistics based on IMS context
        // Assuming 'Lockers' don't exist in our current context, let's adapt to Tickets and Users.
        // If you actually meant something else by 'Lockers' or 'Requests', let me know!
        // But since the image says 'Issues' (Tickets) and 'Users', we'll get those.

        $userStats = [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'supervisors' => User::where('role', 'supervisor')->count(),
            'technicals' => User::where('role', 'technical')->count(),
        ];

        $ticketStats = [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'Open')->count(),
            'in_progress' => Ticket::where('status', 'In Progress')->count(),
            'on_hold' => Ticket::where('status', 'On Hold')->count(),
            'resolved' => Ticket::where('status', 'Resolved')->count(),
            'closed' => Ticket::where('status', 'Closed')->count(),
        ];

        $commentStats = [
            'total' => TicketComment::count(),
            'reactions' => TicketCommentReaction::count(),
            'votes' => TicketCommentVote::count(),
        ];

        $auditLogCount = TicketActivity::count();

        // System Logs
        $laravelLogPath = storage_path('logs/laravel.log');
        $laravelLog = file_exists($laravelLogPath)
            ? implode("\n", array_slice(file($laravelLogPath), -50))
            : 'No log file found.';

        $phpErrorLogPath = 'C:\\xampp\\apache\\logs\\error.log';
        $phpErrorLog = file_exists($phpErrorLogPath)
            ? implode("\n", array_slice(file($phpErrorLogPath), -50))
            : 'No log file found or error_log not set.';

        // DB Size
        $dbName = DB::connection()->getDatabaseName();
        $dbSizeRaw = DB::selectOne("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb' 
        FROM information_schema.TABLES 
        WHERE table_schema = ?
    ", [$dbName]);
        $dbSize = round((float) ($dbSizeRaw->size_mb ?? 0), 2);

        $backupsDisk = Storage::disk('backups');
        $backupsTotalBytes = collect($backupsDisk->files())
            ->filter(fn (string $file) => str_ends_with($file, '.sql'))
            ->sum(fn (string $file) => $backupsDisk->size($file));
        $backupsTotalSizeMb = round($backupsTotalBytes / 1024 / 1024, 2);

        return Inertia::render('Diagnostics', [
            'phpVersion' => phpversion(),
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'debugMode' => config('app.debug'),
            'dbConnection' => config('database.default'),
            'dbName' => $dbName,
            'dbSizeMb' => $dbSize,
            'backupsTotalSizeMb' => $backupsTotalSizeMb,
            'cacheDriver' => config('cache.default'),
            'queueDriver' => config('queue.default'),
            'serverInfo' => php_uname(),
            'timezone' => config('app.timezone'),
            'serverTime' => now()->format('n/j/Y, g:i:s A'),
            'storageWritable' => is_writable(storage_path()),
            'dbStats' => [
                'users' => $userStats,
                'tickets' => $ticketStats,
                'comments' => $commentStats,
                'auditLogCount' => $auditLogCount,
            ],
            'logs' => [
                'laravel' => [
                    'path' => $laravelLogPath,
                    'content' => $laravelLog,
                    'size' => file_exists($laravelLogPath) ? round(filesize($laravelLogPath) / 1024 / 1024, 2).' MB' : '0 MB',
                ],
                'php' => [
                    'path' => $phpErrorLogPath,
                    'content' => $phpErrorLog,
                    'size' => file_exists($phpErrorLogPath) ? round(filesize($phpErrorLogPath) / 1024 / 1024, 2).' MB' : '0 MB',
                ],
            ],
            'backups' => collect(Storage::disk('backups')->files())
                ->filter(fn (string $file) => str_ends_with($file, '.sql'))
                ->map(function (string $file) {
                    $disk = Storage::disk('backups');

                    return [
                        'name' => basename($file),
                        'size' => round($disk->size($file) / 1024 / 1024, 2).' MB',
                        'date' => Carbon::createFromTimestamp($disk->lastModified($file))->format('Y-m-d H:i:s'),
                    ];
                })
                ->sortByDesc('date')
                ->values(),
        ]);
    })->name('diagnostics');

    Route::post('diagnostics/backup', function () {
        $disk = Storage::disk('backups');
        $filename = 'ims-backup-'.now()->format('Y-m-d-H-i-s').'.sql';
        $backupsDir = storage_path('app/backups');
        if (! is_dir($backupsDir)) {
            mkdir($backupsDir, 0755, true);
        }
        $path = $disk->path($filename);

        // Using mysqldump directly since it's an XAMPP setup usually
        $dbName = DB::connection()->getDatabaseName();
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host');

        $passwordArg = $dbPass ? "-p\"{$dbPass}\"" : '';
        $command = "C:\\xampp\\mysql\\bin\\mysqldump -h {$dbHost} -u {$dbUser} {$passwordArg} {$dbName} > \"{$path}\" 2>&1";

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            return back()->with('error', 'Backup failed: '.implode("\n", $output));
        }

        return back()->with('success', 'Backup created successfully.');
    })->name('diagnostics.backup');

    Route::delete('diagnostics/backup/{filename}', function (string $filename) {
        $filename = basename($filename);
        $disk = Storage::disk('backups');
        if ($disk->exists($filename)) {
            $disk->delete($filename);

            return back()->with('success', 'Backup deleted.');
        }

        return back()->with('error', 'Backup not found.');
    })->name('diagnostics.backup.delete');

    Route::get('diagnostics/backup/{filename}/download', function (string $filename) {
        $filename = basename($filename);
        $disk = Storage::disk('backups');
        if ($disk->exists($filename)) {
            return $disk->download($filename);
        }
        abort(404);
    })->name('diagnostics.backup.download');

    Route::get('diagnostics/phpinfo', function () {
        return Inertia::render('PhpInfo');
    })->name('diagnostics.phpinfo');

    Route::get('diagnostics/phpinfo/data', function () {
        phpinfo();
        exit;
    })->name('diagnostics.phpinfo.data');

    // ── Admin Settings ─────────────────────────────────────────────────────────
    Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::put('ticket-categories', [SettingsController::class, 'updateCategories'])->name('admin.ticket-categories.update');
    Route::delete('ticket-categories/{ticketCategory}/tickets', [SettingsController::class, 'destroyTicketsForCategory'])->name('admin.ticket-categories.tickets.destroy');
    Route::put('ticket-priorities', [SettingsController::class, 'updatePriorities'])->name('admin.ticket-priorities.update');
    Route::delete('ticket-priorities/{ticketPriority}/tickets', [SettingsController::class, 'destroyTicketsForPriority'])->name('admin.ticket-priorities.tickets.destroy');
    Route::put('ticket-statuses', [SettingsController::class, 'updateStatuses'])->name('admin.ticket-statuses.update');
    Route::delete('ticket-statuses/{ticketStatus}/tickets', [SettingsController::class, 'destroyTicketsForStatus'])->name('admin.ticket-statuses.tickets.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
