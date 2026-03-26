<?php

use App\Http\Controllers\Admin\SettingsController;
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
            'reporterId' => $ticket->user_id,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
            'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
        ])->all();

    $recentComments = TicketComment::with(['user', 'ticket.reporter', 'ticket.handlers'])
        ->latest()
        ->take(5)
        ->get()
        ->map(function ($comment) {
            $name = $comment->user->name ?? 'Unknown';
            $initials = collect(explode(' ', $name))
                ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
                ->take(2)
                ->implode('');
            $plain = strip_tags($comment->body);
            $ticket = $comment->ticket;

            return [
                'id' => $comment->id,
                'userName' => $name,
                'userInitials' => $initials,
                'userRole' => $comment->user->role ?? 'user',
                'bodySnippet' => mb_strimwidth($plain, 0, 120, '…'),
                'ticketNumericId' => $ticket->id ?? null,
                'ticketTktId' => 'TKT-'.(1000 + ($ticket->id ?? 0)),
                'ticketTitle' => $ticket->title ?? '',
                'ticketDescription' => $ticket->description ?? null,
                'ticketStatus' => $ticket->status ?? '',
                'ticketPriority' => $ticket->priority ?? '',
                'ticketCategory' => $ticket->category ?? '',
                'ticketReporter' => $ticket->reporter?->name ?? 'Unknown',
                'ticketReporterId' => $ticket->user_id ?? null,
                'ticketCreatedAtFormatted' => $ticket?->created_at?->format('M d, Y \a\t h:i A') ?? '',
                'ticketHandlerIds' => $ticket?->handlers?->pluck('id')->toArray() ?? [],
                'ticketHandlers' => $ticket?->handlers?->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray() ?? [],
                'ticketAttachmentUrl' => ($ticket && $ticket->attachment) ? Storage::disk('public')->url($ticket->attachment) : null,
                'createdAt' => $comment->created_at->diffForHumans(),
            ];
        });

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'trendData' => $trendData,
        'chartTrend' => $chartTrend,
        'severities' => $severities,
        'categories' => $categories,
        'topRecurring' => $topRecurring,
        'recentActivity' => $recentActivity,
        'recentComments' => $recentComments,
        'users' => User::select('id', 'name')->orderBy('name')->get(),
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::get('tickets/by-priority/{priority}', function (string $priority) {
    $tickets = Ticket::with(['reporter', 'handlers'])
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
            'reporter' => $t->reporter?->name ?? 'Unknown',
            'reporterId' => $t->user_id,
            'handlerIds' => $t->handlers->pluck('id')->toArray(),
            'handlers' => $t->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'attachmentUrl' => $t->attachment ? Storage::disk('public')->url($t->attachment) : null,
            'createdAtFormatted' => $t->created_at->format('M d, Y \a\t h:i A'),
            'time' => $t->created_at->diffForHumans(),
        ]);

    return response()->json($tickets);
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.by-priority');

Route::get('tickets/by-category/{category}', function (string $category) {
    $tickets = Ticket::with(['reporter', 'handlers'])
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
            'reporter' => $t->reporter?->name ?? 'Unknown',
            'reporterId' => $t->user_id,
            'handlerIds' => $t->handlers->pluck('id')->toArray(),
            'handlers' => $t->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
            'attachmentUrl' => $t->attachment ? Storage::disk('public')->url($t->attachment) : null,
            'createdAtFormatted' => $t->created_at->format('M d, Y \a\t h:i A'),
            'time' => $t->created_at->diffForHumans(),
        ]);

    return response()->json($tickets);
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.by-category');

Route::get('tickets', function () {
    $tickets = Ticket::with(['handlers', 'reporter'])
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
        'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name']),
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets');

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

Route::post('tickets', function () {
    $validated = request()->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => ['required', 'string', Rule::in(TicketCategory::pluck('name')->toArray())],
        'priority' => ['required', 'string', Rule::in(TicketPriority::pluck('name')->toArray())],
        'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
        'handler_ids' => [
            Rule::requiredIf(fn () => in_array(request('status'), ['In Progress', 'On Hold', 'Resolved'])),
            'nullable',
            'array',
        ],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.store');

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

    $newStatus = $validated['status'];
    $tickets = Ticket::with(['reporter', 'handlers'])->whereIn('id', $validated['ticket_ids'])->get();

    // Capture old statuses before bulk update
    $oldStatuses = $tickets->pluck('status', 'id');

    Ticket::whereIn('id', $validated['ticket_ids'])->update([
        'status' => $newStatus,
        'resolved_at' => $newStatus === 'Resolved' ? now() : null,
        'solution' => $newStatus === 'Resolved' ? ($validated['solution'] ?? null) : null,
    ]);

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
    // - Open → always clear (empty array)
    // - Other statuses → sync provided handler_ids (may be empty for Closed optional case)
    $handlerIds = $newStatus === 'Open' ? [] : ($validated['handler_ids'] ?? null);
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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.status');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.handlers');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.bulk.destroy');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.destroy');

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

    if (! empty($validated['handler_ids'])) {
        $existingHandlerIds = $ticket->handlers()->pluck('users.id')->toArray();
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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.status.update');

Route::patch('tickets/{ticket}/handlers', function (Ticket $ticket) {
    $validated = request()->validate([
        'handler_ids' => ['required', 'array', 'min:1'],
        'handler_ids.*' => ['exists:users,id'],
        'status' => ['nullable', 'string', Rule::in(TicketStatus::pluck('name')->where(fn ($n) => $n !== 'Open')->values()->toArray())],
        'solution' => [
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

    // Log handler assignment changes
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
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.handlers.update');

Route::put('tickets/{ticket}', function (Ticket $ticket) {
    $validated = request()->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => ['required', 'string', Rule::in(TicketCategory::pluck('name')->toArray())],
        'priority' => ['required', 'string', Rule::in(TicketPriority::pluck('name')->toArray())],
        'status' => ['required', 'string', Rule::in(TicketStatus::pluck('name')->toArray())],
        'handler_ids' => [
            Rule::requiredIf(fn () => in_array(request('status'), ['In Progress', 'On Hold', 'Resolved'])),
            'nullable',
            'array',
        ],
        'handler_ids.*' => ['exists:users,id'],
        'solution' => [
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
})->middleware(['auth', 'verified', 'role:admin'])->name('users.store');

Route::patch('users/{user}', function (User $user) {
    $validated = request()->validate([
        'name' => 'required|string|max:255',
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        'password' => 'nullable|string|min:8|confirmed',
        'role' => 'required|string|in:admin,supervisor,technical',
    ]);

    $changes = [];
    if ($user->name !== $validated['name']) {
        $changes[] = "name: {$user->name} → {$validated['name']}";
    }
    if ($user->email !== $validated['email']) {
        $changes[] = "email: {$user->email} → {$validated['email']}";
    }

    $user->name = $validated['name'];
    $user->email = $validated['email'];

    // Prevent admins from changing their own role
    if ($user->id !== auth()->id()) {
        if ($user->role !== $validated['role']) {
            $changes[] = "role: {$user->role} → {$validated['role']}";
        }
        $user->role = $validated['role'];
    }

    if (! empty($validated['password'])) {
        $changes[] = 'password changed';
        $user->password = $validated['password'];
    }

    $user->save();

    if (! empty($changes)) {
        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'user_updated',
            'old_value' => $user->name,
            'new_value' => implode('; ', $changes),
            'created_at' => now(),
        ]);
    }

    return redirect()->back()->with('success', 'User updated successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('users.update');

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

// ── Ticket Comments ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    $allowedEmojis = ['👍', '❤️', '😂', '😮', '😢', '🎉', '🔥', '✅'];

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

// ── Audit Log ──────────────────────────────────────────────────────────────
Route::get('audit-log', function () {
    $query = TicketActivity::with(['user:id,name', 'ticket:id,title'])
        ->orderByDesc('created_at')
        ->orderByDesc('id');

    if ($action = request('action')) {
        $query->where('action', $action);
    }
    if ($userId = request('user_id')) {
        $query->where('user_id', $userId);
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
            'userId' => $a->user_id,
            'ticketId' => $a->ticket_id,
            'ticketTitle' => $a->ticket?->title ?? '—',
            'ticketTktId' => $a->ticket_id ? 'TKT-'.(1000 + $a->ticket_id) : '—',
            'createdAt' => $a->created_at->diffForHumans(),
            'createdAtFormatted' => $a->created_at->format('M d, Y \a\t h:i A'),
        ]),
        'filters' => request()->only(['action', 'user_id', 'search', 'from', 'to']),
        'users' => User::select('id', 'name')->orderBy('name')->get(),
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('audit-log');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics');

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
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics.backup');

Route::delete('diagnostics/backup/{filename}', function (string $filename) {
    $filename = basename($filename);
    $disk = Storage::disk('backups');
    if ($disk->exists($filename)) {
        $disk->delete($filename);

        return back()->with('success', 'Backup deleted.');
    }

    return back()->with('error', 'Backup not found.');
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics.backup.delete');

Route::get('diagnostics/backup/{filename}/download', function (string $filename) {
    $filename = basename($filename);
    $disk = Storage::disk('backups');
    if ($disk->exists($filename)) {
        return $disk->download($filename);
    }
    abort(404);
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics.backup.download');

Route::get('diagnostics/phpinfo', function () {
    return Inertia::render('PhpInfo');
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics.phpinfo');

Route::get('diagnostics/phpinfo/data', function () {
    phpinfo();
    exit;
})->middleware(['auth', 'verified', 'role:admin'])->name('diagnostics.phpinfo.data');

// ── Admin Settings ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::put('admin/ticket-categories', [SettingsController::class, 'updateCategories'])->name('admin.ticket-categories.update');
    Route::put('admin/ticket-priorities', [SettingsController::class, 'updatePriorities'])->name('admin.ticket-priorities.update');
    Route::put('admin/ticket-statuses', [SettingsController::class, 'updateStatuses'])->name('admin.ticket-statuses.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
