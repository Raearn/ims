<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        $data = $this->buildDashboardData(request('period', '7d'));

        $data['users'] = User::select('id', 'name')->orderBy('name')->get();
        $data['statuses'] = TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']);

        return Inertia::render('Dashboard', $data);
    }

    public function exportPdf(): Response
    {
        $period = request('period', '7d');
        $data = $this->buildDashboardData($period);

        /** @var array{start: Carbon, end: Carbon} $window */
        $window = $data['_window'];
        $currentPeriodStart = $window['start'];
        $now = $window['end'];

        $priorityOrder = ['Critical', 'High', 'Medium', 'Low'];

        $ticketsByPriority = Ticket::with(['reporter', 'handlers'])
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->orderByRaw("FIELD(priority, 'Critical','High','Medium','Low')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('priority')
            ->map(fn ($tickets) => $tickets->map(fn ($t) => [
                'tktId' => 'TKT-'.(1000 + $t->id),
                'title' => $t->title,
                'category' => $t->category,
                'status' => $t->status,
                'reporter' => $t->reporter?->name ?? 'Unknown',
                'handlers' => $t->handlers->pluck('name')->join(', ') ?: '—',
                'openedAt' => $t->created_at->format('M d, Y'),
            ])->values()->all())
            ->sortBy(fn ($_, $key) => array_search($key, $priorityOrder))
            ->all();

        $ticketsByCategory = Ticket::with(['reporter', 'handlers'])
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->orderBy('category')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('category')
            ->map(fn ($tickets) => $tickets->map(fn ($t) => [
                'tktId' => 'TKT-'.(1000 + $t->id),
                'title' => $t->title,
                'priority' => $t->priority,
                'status' => $t->status,
                'reporter' => $t->reporter?->name ?? 'Unknown',
                'handlers' => $t->handlers->pluck('name')->join(', ') ?: '—',
                'openedAt' => $t->created_at->format('M d, Y'),
            ])->values()->all())
            ->all();

        unset($data['_window']);

        $pdf = Pdf::loadView('exports.dashboard-report', array_merge($data, [
            'ticketsByPriority' => $ticketsByPriority,
            'ticketsByCategory' => $ticketsByCategory,
            'generatedAt' => now()->format('F j, Y \a\t g:i A'),
            'priorityOrder' => $priorityOrder,
        ]));

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("dashboard-report-{$period}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(string $period): array
    {
        $now = now();

        switch ($period) {
            case '30d':
                $currentPeriodStart = $now->copy()->subDays(30)->startOfDay();
                $previousPeriodStart = $now->copy()->subDays(60)->startOfDay();
                break;
            case 'this_month':
                $currentPeriodStart = $now->copy()->startOfMonth();
                $previousPeriodStart = $now->copy()->subMonth()->startOfMonth();
                break;
            case 'last_month':
                $currentPeriodStart = $now->copy()->subMonth()->startOfMonth();
                $previousPeriodStart = $now->copy()->subMonths(2)->startOfMonth();
                $now = $currentPeriodStart->copy()->endOfMonth();
                break;
            case 'ytd':
                $currentPeriodStart = $now->copy()->startOfYear();
                $previousPeriodStart = $now->copy()->subYear()->startOfYear();
                break;
            case 'all':
                $currentPeriodStart = Carbon::create(2000, 1, 1);
                $previousPeriodStart = Carbon::create(2000, 1, 1);
                break;
            case '7d':
            default:
                $currentPeriodStart = $now->copy()->subDays(7)->startOfDay();
                $previousPeriodStart = $now->copy()->subDays(14)->startOfDay();
                break;
        }

        $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

        $periodLabel = match ($period) {
            '30d' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'ytd' => 'Year to Date',
            'all' => 'All Time',
            default => 'Last 7 Days',
        };

        $periodComparisonLabel = match ($period) {
            '30d' => 'Compared with the prior 30 days',
            'this_month' => 'Compared with the previous month',
            'last_month' => 'Compared with the month before',
            'ytd' => 'Compared with the same period last year',
            'all' => 'Compared with the prior window',
            default => 'Compared with the prior 7 days',
        };

        $trendFromCounts = function (float $current, float $previous): array {
            if ($previous <= 0.0) {
                if ($current <= 0.0) {
                    return ['display' => '—', 'value' => 0.0, 'isUp' => true, 'showTrendArrow' => false];
                }

                return ['display' => 'New', 'value' => null, 'isUp' => true, 'showTrendArrow' => true];
            }

            $pct = round(abs($current - $previous) / $previous * 100, 1);

            return [
                'display' => $pct.'%',
                'value' => $pct,
                'isUp' => $current >= $previous,
                'showTrendArrow' => true,
            ];
        };

        $trendFromAverages = function (float $current, float $previous): array {
            if ($previous <= 0.0) {
                return ['display' => '—', 'value' => null, 'isUp' => true, 'showTrendArrow' => false];
            }

            if ($current <= 0.0) {
                return ['display' => '—', 'value' => null, 'isUp' => false, 'showTrendArrow' => false];
            }

            $pct = round(abs($current - $previous) / $previous * 100, 1);

            return [
                'display' => $pct.'%',
                'value' => $pct,
                'isUp' => $current >= $previous,
                'showTrendArrow' => true,
            ];
        };

        // ── Core counts ───────────────────────────────────────────────
        $openCount = Ticket::where('status', 'Open')->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count();
        $inProgressCount = Ticket::where('status', 'In Progress')->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count();
        $resolvedForStatWidgets = ['Resolved'];
        $resolvedCount = Ticket::query()
            ->whereIn('status', $resolvedForStatWidgets)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $currentPeriodStart)
            ->where('resolved_at', '<=', $now)
            ->count();

        $avgHours = (float) (Ticket::whereNotNull('resolved_at')
            ->whereIn('status', $resolvedForStatWidgets)
            ->where('resolved_at', '>=', $currentPeriodStart)
            ->where('resolved_at', '<=', $now)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')
            ->value('h') ?? 0);

        // ── Period-over-period ────────────────────────────────────────
        $openTrend = $trendFromCounts(
            (float) Ticket::where('status', 'Open')->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count(),
            (float) Ticket::where('status', 'Open')->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count()
        );
        $inProgressTrend = $trendFromCounts(
            (float) Ticket::where('status', 'In Progress')->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count(),
            (float) Ticket::where('status', 'In Progress')->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count()
        );
        $resolvedTrend = $trendFromCounts(
            (float) Ticket::whereIn('status', $resolvedForStatWidgets)->whereNotNull('resolved_at')->where('resolved_at', '>=', $currentPeriodStart)->where('resolved_at', '<=', $now)->count(),
            (float) Ticket::whereIn('status', $resolvedForStatWidgets)->whereNotNull('resolved_at')->whereBetween('resolved_at', [$previousPeriodStart, $previousPeriodEnd])->count()
        );
        $thisWeekAvgHours = (float) (Ticket::whereNotNull('resolved_at')->whereIn('status', $resolvedForStatWidgets)->where('resolved_at', '>=', $currentPeriodStart)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')->value('h') ?? 0);
        $lastWeekAvgHours = (float) (Ticket::whereNotNull('resolved_at')->whereIn('status', $resolvedForStatWidgets)->whereBetween('resolved_at', [$previousPeriodStart, $previousPeriodEnd])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')->value('h') ?? 0);
        $avgTimeTrend = $trendFromAverages($thisWeekAvgHours, $lastWeekAvgHours);

        $trendDays = min((int) ceil($currentPeriodStart->diffInDays($now)), 90);
        if ($trendDays < 1 || $period === 'all') {
            $trendDays = 90;
        }

        // ── Sparklines ────────────────────────────────────────────────
        $sparkPoints = min($trendDays, 30);
        $sparkStart = $now->copy()->subDays($sparkPoints - 1)->startOfDay();
        $sparkDates = collect(range($sparkPoints - 1, 0))->map(fn ($i) => $now->copy()->subDays($i)->format('Y-m-d'));

        $dailyCounts = function (string $dateCol, string|array|null $status = null) use ($sparkStart, $sparkDates): array {
            $raw = Ticket::selectRaw("DATE($dateCol) as d, COUNT(*) as c")
                ->where($dateCol, '>=', $sparkStart)
                ->when(is_string($status), fn ($q) => $q->where('status', $status))
                ->when(is_array($status), fn ($q) => $q->whereIn('status', $status))
                ->groupBy('d')
                ->pluck('c', 'd');

            return $sparkDates->map(fn ($d) => (int) ($raw->get($d, 0)))->values()->all();
        };

        $avgTimeSparkRaw = Ticket::selectRaw('DATE(resolved_at) as d, AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 as h')
            ->whereNotNull('resolved_at')
            ->whereIn('status', $resolvedForStatWidgets)
            ->where('resolved_at', '>=', $sparkStart)
            ->groupBy('d')
            ->pluck('h', 'd');
        $avgTimeSparkline = $sparkDates->map(fn ($d) => round((float) ($avgTimeSparkRaw->get($d, 0)), 1))->values()->all();

        $sparklineLabels = $sparkDates
            ->map(fn ($d) => Carbon::parse($d)->format('M j'))
            ->values()
            ->all();

        // ── Stats payload ─────────────────────────────────────────────
        $stats = [
            [
                'title' => 'Total Open Incidents',
                'value' => $openCount,
                'description' => $periodComparisonLabel,
                'trend' => $openTrend['display'],
                'isUp' => $openTrend['isUp'],
                'showTrendArrow' => $openTrend['showTrendArrow'],
                'sparkline' => $dailyCounts('created_at', 'Open'),
                'sparklineValueSuffix' => '',
                'stroke' => '#f43f5e',
                'textColor' => 'text-rose-600 dark:text-rose-400',
                'cardBg' => 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-100/50 dark:border-rose-900/50',
            ],
            [
                'title' => 'Pending Review',
                'value' => $inProgressCount,
                'description' => 'Work in progress · '.$periodComparisonLabel,
                'trend' => $inProgressTrend['display'],
                'isUp' => $inProgressTrend['isUp'],
                'showTrendArrow' => $inProgressTrend['showTrendArrow'],
                'sparkline' => $dailyCounts('created_at', 'In Progress'),
                'sparklineValueSuffix' => '',
                'stroke' => '#f97316',
                'textColor' => 'text-orange-600 dark:text-orange-400',
                'cardBg' => 'bg-orange-50/50 dark:bg-orange-950/20 border-orange-100/50 dark:border-orange-900/50',
            ],
            [
                'title' => 'Resolved Incidents',
                'value' => $resolvedCount,
                'description' => 'Status Resolved · '.$periodComparisonLabel,
                'trend' => $resolvedTrend['display'],
                'isUp' => $resolvedTrend['isUp'],
                'showTrendArrow' => $resolvedTrend['showTrendArrow'],
                'sparkline' => $dailyCounts('resolved_at', $resolvedForStatWidgets),
                'sparklineValueSuffix' => '',
                'stroke' => '#3b82f6',
                'textColor' => 'text-blue-600 dark:text-blue-400',
                'cardBg' => 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-100/50 dark:border-blue-900/50',
            ],
            [
                'title' => 'Avg. Resolution Time',
                'value' => $avgHours > 0 ? round($avgHours, 1).'h' : '—',
                'description' => 'Mean hours (Resolved only) · '.$periodComparisonLabel,
                'trend' => $avgTimeTrend['display'],
                'isUp' => ! $avgTimeTrend['isUp'],
                'showTrendArrow' => $avgTimeTrend['showTrendArrow'],
                'sparkline' => $avgTimeSparkline,
                'sparklineValueSuffix' => 'h',
                'stroke' => '#10b981',
                'textColor' => 'text-emerald-600 dark:text-emerald-400',
                'cardBg' => 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-100/50 dark:border-emerald-900/50',
            ],
        ];

        // ── Trend chart ────────────────────────────────────────────────
        $trendStart = $now->copy()->subDays($trendDays - 1)->startOfDay();

        $createdRaw = Ticket::selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('created_at', '>=', $trendStart)
            ->where('created_at', '<=', $now)
            ->groupBy('d')
            ->pluck('c', 'd');
        $resolvedRaw = Ticket::selectRaw('DATE(resolved_at) as d, COUNT(*) as c')
            ->whereNotNull('resolved_at')
            ->whereIn('status', $resolvedForStatWidgets)
            ->where('resolved_at', '>=', $trendStart)
            ->where('resolved_at', '<=', $now)
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

        $chartTrendRaw = $trendFromCounts(
            (float) Ticket::where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count(),
            (float) Ticket::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count()
        );
        $chartTrend = [
            'value' => $chartTrendRaw['value'],
            'isUp' => $chartTrendRaw['isUp'],
            'display' => $chartTrendRaw['display'],
            'showTrendArrow' => $chartTrendRaw['showTrendArrow'],
        ];

        // ── Priority distribution ─────────────────────────────────────
        $priorityMap = [
            'Critical' => ['color' => 'bg-rose-500',   'hex' => '#f43f5e'],
            'High' => ['color' => 'bg-orange-500',  'hex' => '#f97316'],
            'Medium' => ['color' => 'bg-yellow-500',  'hex' => '#eab308'],
            'Low' => ['color' => 'bg-blue-400',    'hex' => '#60a5fa'],
        ];
        $priorityRaw = Ticket::selectRaw('priority, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('priority')
            ->pluck('c', 'priority');
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
        $categoryRaw = Ticket::selectRaw('category, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('category')
            ->pluck('c', 'category');
        $categories = collect($categoryMap)
            ->map(fn ($c, $name) => ['name' => $name, 'count' => (int) $categoryRaw->get($name, 0), 'color' => $c['color'], 'hex' => $c['hex']])
            ->filter(fn ($c) => $c['count'] > 0)
            ->values()->all();

        $extraCats = $categoryRaw
            ->filter(fn ($count, $name) => ! isset($categoryMap[$name]) && $count > 0)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => (int) $count, 'color' => 'bg-gray-500', 'hex' => '#6b7280'])
            ->values()->all();
        $categories = array_values(array_merge($categories, $extraCats));

        // ── Top recurring incidents ───────────────────────────────────
        $thisMonthCounts = Ticket::selectRaw('title, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('title')
            ->pluck('c', 'title');
        $lastMonthCounts = Ticket::selectRaw('title, COUNT(*) as c')
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->groupBy('title')
            ->pluck('c', 'title');

        $topRecurring = Ticket::selectRaw('title, category, COUNT(*) as total')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
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
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
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

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'stats' => $stats,
            'sparklineLabels' => $sparklineLabels,
            'trendData' => $trendData,
            'chartTrend' => $chartTrend,
            'severities' => $severities,
            'categories' => $categories,
            'topRecurring' => $topRecurring,
            'recentActivity' => $recentActivity,
            'recentComments' => $recentComments,
            '_window' => ['start' => $currentPeriodStart, 'end' => $now],
        ];
    }
}
