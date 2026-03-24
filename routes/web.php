<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

Route::get('dashboard', function () {
    $now = now();
    $thisWeekStart = $now->copy()->startOfWeek();
    $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
    $lastWeekEnd = $thisWeekStart->copy()->subSecond();
    $monthStart = $now->copy()->startOfMonth();
    $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
    $prevMonthEnd = $monthStart->copy()->subSecond();

    $trendPct = function (int|float $current, int|float $previous): array {
        if ($previous == 0) {
            return ['value' => $current > 0 ? 100.0 : 0.0, 'isUp' => $current > 0];
        }

        return [
            'value' => round(abs($current - $previous) / $previous * 100, 1),
            'isUp' => $current >= $previous,
        ];
    };

    // ── Core counts ───────────────────────────────────────────────
    $openCount = Ticket::where('status', 'Open')->count();
    $inProgressCount = Ticket::where('status', 'In Progress')->count();
    $resolvedCount = Ticket::where('status', 'Resolved')->count();
    $avgHours = (float) (Ticket::whereNotNull('resolved_at')
        ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')
        ->value('h') ?? 0);

    // ── Week-over-week ────────────────────────────────────────────
    $openTrend = $trendPct(
        Ticket::where('status', 'Open')->where('created_at', '>=', $thisWeekStart)->count(),
        Ticket::where('status', 'Open')->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count()
    );
    $inProgressTrend = $trendPct(
        Ticket::where('status', 'In Progress')->where('created_at', '>=', $thisWeekStart)->count(),
        Ticket::where('status', 'In Progress')->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count()
    );
    $resolvedTrend = $trendPct(
        Ticket::where('status', 'Resolved')->where('resolved_at', '>=', $thisWeekStart)->count(),
        Ticket::where('status', 'Resolved')->whereBetween('resolved_at', [$lastWeekStart, $lastWeekEnd])->count()
    );
    $thisWeekAvgHours = (float) (Ticket::whereNotNull('resolved_at')->where('resolved_at', '>=', $thisWeekStart)
        ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')->value('h') ?? 0);
    $lastWeekAvgHours = (float) (Ticket::whereNotNull('resolved_at')->whereBetween('resolved_at', [$lastWeekStart, $lastWeekEnd])
        ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')->value('h') ?? 0);
    $avgTimeTrend = $trendPct($thisWeekAvgHours, $lastWeekAvgHours);

    // ── Sparklines (last 11 days) ─────────────────────────────────
    $sparkStart = $now->copy()->subDays(10)->startOfDay();
    $sparkDates = collect(range(10, 0))->map(fn ($i) => $now->copy()->subDays($i)->format('Y-m-d'));

    $dailyCounts = function (string $dateCol, ?string $status = null) use ($sparkStart, $sparkDates): array {
        $raw = Ticket::selectRaw("DATE($dateCol) as d, COUNT(*) as c")
            ->where($dateCol, '>=', $sparkStart)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->groupBy('d')
            ->pluck('c', 'd');

        return $sparkDates->map(fn ($d) => (int) ($raw->get($d, 0)))->values()->all();
    };

    $avgTimeSparkRaw = Ticket::selectRaw('DATE(resolved_at) as d, AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')
        ->whereNotNull('resolved_at')
        ->where('resolved_at', '>=', $sparkStart)
        ->groupBy('d')
        ->pluck('h', 'd');
    $avgTimeSparkline = $sparkDates->map(fn ($d) => round((float) ($avgTimeSparkRaw->get($d, 0)), 1))->values()->all();

    // ── Stats payload ─────────────────────────────────────────────
    $stats = [
        [
            'title' => 'Total Open Incidents',
            'value' => $openCount,
            'description' => 'Since last week',
            'trend' => $openTrend['value'].'%',
            'isUp' => $openTrend['isUp'],
            'sparkline' => $dailyCounts('created_at', 'Open'),
            'stroke' => '#f43f5e',
            'textColor' => 'text-rose-600 dark:text-rose-400',
            'cardBg' => 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-100/50 dark:border-rose-900/50',
        ],
        [
            'title' => 'Pending Review',
            'value' => $inProgressCount,
            'description' => 'Work in progress',
            'trend' => $inProgressTrend['value'].'%',
            'isUp' => $inProgressTrend['isUp'],
            'sparkline' => $dailyCounts('created_at', 'In Progress'),
            'stroke' => '#f97316',
            'textColor' => 'text-orange-600 dark:text-orange-400',
            'cardBg' => 'bg-orange-50/50 dark:bg-orange-950/20 border-orange-100/50 dark:border-orange-900/50',
        ],
        [
            'title' => 'Resolved Incidents',
            'value' => $resolvedCount,
            'description' => 'Since last week',
            'trend' => $resolvedTrend['value'].'%',
            'isUp' => $resolvedTrend['isUp'],
            'sparkline' => $dailyCounts('resolved_at', 'Resolved'),
            'stroke' => '#3b82f6',
            'textColor' => 'text-blue-600 dark:text-blue-400',
            'cardBg' => 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-100/50 dark:border-blue-900/50',
        ],
        [
            'title' => 'Avg. Resolution Time',
            'value' => $avgHours > 0 ? round($avgHours, 1).'h' : '—',
            'description' => 'Since last week',
            'trend' => $avgTimeTrend['value'].'%',
            'isUp' => ! $avgTimeTrend['isUp'], // higher avg = worse; flip so "down" means improvement
            'sparkline' => $avgTimeSparkline,
            'stroke' => '#10b981',
            'textColor' => 'text-emerald-600 dark:text-emerald-400',
            'cardBg' => 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-100/50 dark:border-emerald-900/50',
        ],
    ];

    // ── Trend chart (last 30 days, two series) ───────────────────
    $trendDays = 30;
    $trendStart = $now->copy()->subDays($trendDays - 1)->startOfDay();

    $createdRaw = Ticket::selectRaw('DATE(created_at) as d, COUNT(*) as c')
        ->where('created_at', '>=', $trendStart)
        ->groupBy('d')
        ->pluck('c', 'd');
    $resolvedRaw = Ticket::selectRaw('DATE(resolved_at) as d, COUNT(*) as c')
        ->whereNotNull('resolved_at')
        ->where('resolved_at', '>=', $trendStart)
        ->groupBy('d')
        ->pluck('c', 'd');

    $trendData = collect(range($trendDays - 1, 0))
        ->map(fn ($i) => $now->copy()->subDays($i))
        ->values()
        ->map(fn ($carbon, $idx) => [
            'x' => $idx,
            'day' => $carbon->format('D'),
            'date' => $carbon->format('M j'),
            'created' => (int) ($createdRaw->get($carbon->format('Y-m-d'), 0)),
            'resolved' => (int) ($resolvedRaw->get($carbon->format('Y-m-d'), 0)),
        ])->all();

    $chartTrend = $trendPct(
        Ticket::where('created_at', '>=', $thisWeekStart)->count(),
        Ticket::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count()
    );

    // ── Priority distribution ─────────────────────────────────────
    $priorityMap = [
        'Critical' => ['color' => 'bg-rose-500',   'hex' => '#f43f5e'],
        'High' => ['color' => 'bg-orange-500',  'hex' => '#f97316'],
        'Medium' => ['color' => 'bg-yellow-500',  'hex' => '#eab308'],
        'Low' => ['color' => 'bg-blue-400',    'hex' => '#60a5fa'],
    ];
    $priorityRaw = Ticket::selectRaw('priority, COUNT(*) as c')->groupBy('priority')->pluck('c', 'priority');
    $severities = collect($priorityMap)
        ->map(fn ($c, $name) => ['name' => $name, 'count' => (int) $priorityRaw->get($name, 0), 'color' => $c['color'], 'hex' => $c['hex']])
        ->values()->all();

    // ── Category distribution ─────────────────────────────────────
    $categoryMap = [
        'Network' => ['color' => 'bg-blue-500',   'hex' => '#3b82f6'],
        'Hardware' => ['color' => 'bg-purple-500',  'hex' => '#a855f7'],
        'Software' => ['color' => 'bg-orange-500',  'hex' => '#f97316'],
        'Access' => ['color' => 'bg-green-500',   'hex' => '#22c55e'],
        'Security' => ['color' => 'bg-red-500',     'hex' => '#ef4444'],
    ];
    $categoryRaw = Ticket::selectRaw('category, COUNT(*) as c')->groupBy('category')->pluck('c', 'category');
    $categories = collect($categoryMap)
        ->map(fn ($c, $name) => ['name' => $name, 'count' => (int) $categoryRaw->get($name, 0), 'color' => $c['color'], 'hex' => $c['hex']])
        ->filter(fn ($c) => $c['count'] > 0)
        ->values()->all();

    // Include any categories not in the predefined map
    $extraCats = $categoryRaw
        ->filter(fn ($count, $name) => ! isset($categoryMap[$name]) && $count > 0)
        ->map(fn ($count, $name) => ['name' => $name, 'count' => (int) $count, 'color' => 'bg-gray-500', 'hex' => '#6b7280'])
        ->values()->all();
    $categories = array_values(array_merge($categories, $extraCats));

    // ── Top recurring incidents ───────────────────────────────────
    $thisMonthCounts = Ticket::selectRaw('title, COUNT(*) as c')
        ->where('created_at', '>=', $monthStart)
        ->groupBy('title')
        ->pluck('c', 'title');
    $lastMonthCounts = Ticket::selectRaw('title, COUNT(*) as c')
        ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
        ->groupBy('title')
        ->pluck('c', 'title');

    $topRecurring = Ticket::selectRaw('title, category, COUNT(*) as total')
        ->groupBy('title', 'category')
        ->orderByDesc('total')
        ->limit(6)
        ->get()
        ->map(function ($item, $i) use ($thisMonthCounts, $lastMonthCounts) {
            $thisM = (int) $thisMonthCounts->get($item->title, 0);
            $lastM = (int) $lastMonthCounts->get($item->title, 0);
            $change = $lastM > 0
                ? (int) round(abs($thisM - $lastM) / $lastM * 100)
                : ($thisM > 0 ? 100 : 0);

            return [
                'rank' => $i + 1,
                'title' => $item->title,
                'category' => $item->category,
                'count' => (int) $item->total,
                'trend' => $thisM >= $lastM ? 'up' : 'down',
                'change' => $change,
            ];
        })->values()->all();

    // ── Recent activity ───────────────────────────────────────────
    $recentActivity = Ticket::with(['reporter', 'handlers'])
        ->where('status', 'Open')
        ->latest('created_at')
        ->limit(3)
        ->get()
        ->map(fn ($ticket, $i) => [
            'id' => $i + 1,
            'numericId' => $ticket->id,
            'tktId' => 'TKT-'.(1000 + $ticket->id),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'time' => $ticket->created_at->diffForHumans(),
            'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
            'reporter' => $ticket->reporter?->name ?? 'Unknown',
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
            'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
        ])->all();

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'trendData' => $trendData,
        'chartTrend' => $chartTrend,
        'severities' => $severities,
        'categories' => $categories,
        'topRecurring' => $topRecurring,
        'recentActivity' => $recentActivity,
        'users' => User::select('id', 'name')->orderBy('name')->get(),
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::get('tickets', function () {
    $tickets = Ticket::with(['handlers', 'reporter'])
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
            'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
            'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'reporter' => $ticket->reporter?->name ?? 'Unknown',
            'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
            'createdAt' => $ticket->created_at->diffForHumans(),
            'createdAtFormatted' => $ticket->created_at->format('M d, Y \a\t h:i A'),
            'createdAtRaw' => $ticket->created_at->format('Y-m-d'),
            'solution' => $ticket->solution,
            'resolvedInDuration' => $ticket->resolved_at
                ? $ticket->resolved_at->diffForHumans($ticket->created_at, CarbonInterface::DIFF_ABSOLUTE, false, 2)
                : null,
            'resolvedAtFormatted' => $ticket->resolved_at?->format('M d, Y \a\t h:i A'),
        ]),
        'users' => User::select('id', 'name')->orderBy('name')->get(),
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets');

Route::post('tickets', function () {
    $validated = request()->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'priority' => 'required|string',
        'status' => 'required|string|in:Open,In Progress,On Hold,Resolved,Closed',
        'handler_ids' => [
            Rule::requiredIf(fn () => in_array(request('status'), ['In Progress', 'On Hold', 'Resolved'])),
            'nullable',
            'array',
        ],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
            Rule::requiredIf(fn () => request('status') === 'Resolved'),
            'nullable',
            'string',
        ],
        'attachment' => 'nullable|image|max:4096',
    ]);

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

    $handlerIds = $validated['handler_ids'] ?? [];
    $ticket->handlers()->sync($handlerIds);

    if (! empty($handlerIds)) {
        User::whereIn('id', $handlerIds)->each(
            fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
        );
    }

    return redirect()->back()->with('success', 'Ticket created successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.store');

Route::patch('tickets/bulk/status', function () {
    $validated = request()->validate([
        'ticket_ids' => ['required', 'array', 'min:1'],
        'ticket_ids.*' => ['exists:tickets,id'],
        'status' => ['required', 'string', Rule::in(['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'])],
        'handler_ids' => ['nullable', 'array'],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
            Rule::requiredIf(fn () => request('status') === 'Resolved'),
            'nullable',
            'string',
        ],
    ]);

    $newStatus = $validated['status'];
    $tickets = Ticket::with(['reporter', 'handlers'])->whereIn('id', $validated['ticket_ids'])->get();

    // Capture old statuses before bulk update
    $oldStatuses = $tickets->pluck('status', 'id');

    Ticket::whereIn('id', $validated['ticket_ids'])->update([
        'status' => $newStatus,
        'resolved_at' => $newStatus === 'Resolved' ? now() : null,
        'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : null,
    ]);

    // Sync handlers on every affected ticket:
    // - Open → always clear (empty array)
    // - Other statuses → sync provided handler_ids (may be empty for Closed optional case)
    $handlerIds = $newStatus === 'Open' ? [] : ($validated['handler_ids'] ?? null);
    if (! is_null($handlerIds)) {
        $tickets->each(fn (Ticket $ticket) => $ticket->handlers()->sync($handlerIds));
    }

    // Fire notifications
    foreach ($tickets as $ticket) {
        $oldStatus = $oldStatuses->get($ticket->id);
        if ($oldStatus !== $newStatus && $ticket->reporter) {
            $ticket->reporter->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.status');

Route::patch('tickets/bulk/handlers', function () {
    $validated = request()->validate([
        'ticket_ids' => ['required', 'array', 'min:1'],
        'ticket_ids.*' => ['exists:tickets,id'],
        'handler_ids' => ['required', 'array', 'min:1'],
        'handler_ids.*' => ['exists:users,id'],
    ]);

    $tickets = Ticket::with('handlers')->whereIn('id', $validated['ticket_ids'])->get();

    $tickets->each(function (Ticket $ticket) use ($validated) {
        $existingHandlerIds = $ticket->handlers->pluck('id')->toArray();
        $ticket->handlers()->sync($validated['handler_ids']);

        $newHandlerIds = array_diff($validated['handler_ids'], $existingHandlerIds);
        if (! empty($newHandlerIds)) {
            User::whereIn('id', $newHandlerIds)->each(
                fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
            );
        }
    });

    return redirect()->back()->with('success', count($validated['ticket_ids']).' ticket(s) updated.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.handlers');

Route::delete('tickets/bulk', function () {
    $validated = request()->validate([
        'ticket_ids' => ['required', 'array', 'min:1'],
        'ticket_ids.*' => ['exists:tickets,id'],
    ]);

    Ticket::whereIn('id', $validated['ticket_ids'])->delete();

    return redirect()->back()->with('success', count($validated['ticket_ids']).' ticket(s) deleted.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.destroy');

Route::delete('tickets/{ticket}', function (Ticket $ticket) {
    if ($ticket->attachment) {
        Storage::disk('public')->delete($ticket->attachment);
    }

    $ticket->handlers()->detach();
    $ticket->delete();

    return redirect()->back()->with('success', 'Ticket deleted successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.destroy');

Route::patch('tickets/{ticket}/status', function (Ticket $ticket) {
    $validated = request()->validate([
        'status' => ['required', 'string', Rule::in(['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'])],
        'handler_ids' => ['nullable', 'array'],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
            Rule::requiredIf(fn () => request('status') === 'Resolved'),
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

    if (! empty($validated['handler_ids'])) {
        $ticket->handlers()->sync($validated['handler_ids']);
    }

    if ($oldStatus !== $newStatus) {
        $ticket->refresh();
        $reporter = $ticket->reporter;
        if ($reporter) {
            $reporter->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
        }
    }

    return redirect()->back()->with('success', 'Ticket status updated.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.status.update');

Route::patch('tickets/{ticket}/handlers', function (Ticket $ticket) {
    $validated = request()->validate([
        'handler_ids' => ['required', 'array', 'min:1'],
        'handler_ids.*' => ['exists:users,id'],
        'status' => ['nullable', 'string', Rule::in(['In Progress', 'On Hold', 'Resolved'])],
        'solution' => [
            Rule::requiredIf(fn () => request('status') === 'Resolved'),
            'nullable',
            'string',
        ],
    ]);

    if ($ticket->status === 'Open' && empty($validated['status'])) {
        abort(422, 'A new status is required when assigning handlers to an Open ticket.');
    }

    $oldStatus = $ticket->status;
    $newStatus = $validated['status'] ?? null;

    if (! empty($newStatus)) {
        $ticket->update([
            'status' => $newStatus,
            'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : $ticket->solution,
        ]);
    }

    $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();
    $ticket->handlers()->sync($validated['handler_ids']);

    // Notify only newly added handlers
    $newHandlerIds = array_diff($validated['handler_ids'], $existingHandlerIds);
    if (! empty($newHandlerIds)) {
        User::whereIn('id', $newHandlerIds)->each(
            fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
        );
    }

    if ($newStatus && $oldStatus !== $newStatus) {
        $ticket->refresh();
        $reporter = $ticket->reporter;
        if ($reporter) {
            $reporter->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
        }
    }

    return redirect()->back()->with('success', 'Handlers updated successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.handlers.update');

Route::put('tickets/{ticket}', function (Ticket $ticket) {
    $validated = request()->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'priority' => 'required|string',
        'status' => 'required|string|in:Open,In Progress,On Hold,Resolved,Closed',
        'handler_ids' => [
            Rule::requiredIf(fn () => in_array(request('status'), ['In Progress', 'On Hold', 'Resolved'])),
            'nullable',
            'array',
        ],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
            Rule::requiredIf(fn () => request('status') === 'Resolved'),
            'nullable',
            'string',
        ],
        'attachment' => 'nullable|image|max:4096',
    ]);

    if (request()->hasFile('attachment')) {
        if ($ticket->attachment) {
            Storage::disk('public')->delete($ticket->attachment);
        }
        $validated['attachment'] = request()->file('attachment')->store('attachments', 'public');
    }

    $oldStatus = $ticket->status;
    $newStatus = $validated['status'];
    $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();

    $ticket->update([
        'title' => $validated['title'],
        'description' => $validated['description'] ?? null,
        'status' => $newStatus,
        'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : $ticket->solution,
        'priority' => $validated['priority'],
        'category' => $validated['category'],
        'attachment' => $validated['attachment'] ?? $ticket->attachment,
    ]);

    $newHandlerIds = $validated['handler_ids'] ?? [];
    $ticket->handlers()->sync($newHandlerIds);

    // Notify newly added handlers
    $addedHandlerIds = array_diff($newHandlerIds, $existingHandlerIds);
    if (! empty($addedHandlerIds)) {
        User::whereIn('id', $addedHandlerIds)->each(
            fn (User $handler) => $handler->notify(new TicketAssigned($ticket))
        );
    }

    // Notify reporter of status change
    if ($oldStatus !== $newStatus) {
        $ticket->refresh();
        $reporter = $ticket->reporter;
        if ($reporter) {
            $reporter->notify(new TicketStatusChanged($ticket, $oldStatus, $newStatus));
        }
    }

    return redirect()->back()->with('success', 'Ticket updated successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.update');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('users');

Route::post('users', function () {
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'role' => 'required|string|in:admin,supervisor,technical',
    ]);

    User::create($validated);

    return redirect()->back()->with('success', 'User created successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('users.store');

Route::patch('users/{user}', function (User $user) {
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        'password' => 'nullable|string|min:8|confirmed',
        'role' => 'required|string|in:admin,supervisor,technical',
    ]);

    $user->name = $validated['name'];
    $user->email = $validated['email'];

    // Prevent admins from changing their own role
    if ($user->id !== auth()->id()) {
        $user->role = $validated['role'];
    }

    if (! empty($validated['password'])) {
        $user->password = $validated['password'];
    }

    $user->save();

    return redirect()->back()->with('success', 'User updated successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('users.update');

Route::delete('users/{user}', function (User $user) {
    if ($user->id === auth()->id()) {
        abort(403, 'You cannot delete your own account.');
    }

    $user->delete();

    return redirect()->back()->with('success', 'User deleted successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('users.destroy');
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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
