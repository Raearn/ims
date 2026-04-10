<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\ReportGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    /**
     * Dashboard chart/modal date window: tickets are filtered by {@see Ticket::$created_at}
     * between the returned start (inclusive) and end (inclusive).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function reportingPeriodBounds(string $period): array
    {
        $period = in_array($period, ['7d', '30d', 'this_month', 'last_month', 'ytd', 'all'], true)
            ? $period
            : '7d';

        $now = now();

        switch ($period) {
            case '30d':
                $currentPeriodStart = $now->copy()->subDays(30)->startOfDay();
                break;
            case 'this_month':
                $currentPeriodStart = $now->copy()->startOfMonth();
                break;
            case 'last_month':
                $currentPeriodStart = $now->copy()->subMonth()->startOfMonth();
                $now = $currentPeriodStart->copy()->endOfMonth();
                break;
            case 'ytd':
                $currentPeriodStart = $now->copy()->startOfYear();
                break;
            case 'all':
                $currentPeriodStart = Carbon::create(2000, 1, 1);
                break;
            case '7d':
            default:
                $currentPeriodStart = $now->copy()->subDays(7)->startOfDay();
                break;
        }

        return [$currentPeriodStart, $now];
    }

    public function index(): InertiaResponse
    {
        $data = $this->buildDashboardData(request('period', '7d'));

        $data['users'] = User::select('id', 'name')->orderBy('name')->get();
        $data['statuses'] = TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']);

        return Inertia::render('Dashboard', $data);
    }

    public function exportPdf(ReportGenerationService $reportService): Response
    {
        $period = request('period', '7d');

        $days = 7;
        switch ($period) {
            case '30d':
                $days = 30;
                break;
            case 'this_month':
                $days = now()->day;
                break;
            case 'last_month':
                $days = 60;
                break;
            case 'ytd':
                $days = now()->dayOfYear;
                break;
            case 'all':
                $days = 365 * 10;
                break;
        }

        $data = $reportService->generateDashboardMetrics($days);

        TicketActivity::create([
            'ticket_id' => null,
            'user_id' => auth()->id(),
            'action' => 'dashboard_export_pdf',
            'old_value' => null,
            'new_value' => 'Reporting period: '.$period,
            'created_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.dashboard-pdf', $data);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("dashboard-report-{$period}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(string $period): array
    {
        $period = in_array($period, ['7d', '30d', 'this_month', 'last_month', 'ytd', 'all'], true)
            ? $period
            : '7d';

        [$currentPeriodStart, $now] = self::reportingPeriodBounds($period);

        $periodLabel = match ($period) {
            '30d' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'ytd' => 'Year to Date',
            'all' => 'All Time',
            default => 'Last 7 Days',
        };

        // ── Core counts ───────────────────────────────────────────────
        $openCount = Ticket::where('status', 'Open')->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count();
        $inProgressCount = Ticket::whereIn('status', ['In Progress', 'On Hold'])->where('created_at', '>=', $currentPeriodStart)->where('created_at', '<=', $now)->count();
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

        $trendDays = min((int) ceil($currentPeriodStart->diffInDays($now)), 90);
        if ($trendDays < 1 || $period === 'all') {
            $trendDays = 90;
        }

        // ── Stat card sparklines (recent daily shape; no period-over-period %) ──
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

        $pendingReviewStatuses = ['In Progress', 'On Hold'];

        // ── Stats payload ─────────────────────────────────────────────
        $stats = [
            [
                'title' => 'Total Open Incidents',
                'value' => $openCount,
                'description' => 'Open tickets created in '.$periodLabel,
                'sparkline' => $dailyCounts('created_at', 'Open'),
                'sparklineValueSuffix' => '',
                'stroke' => '#f43f5e',
            ],
            [
                'title' => 'Pending Review',
                'value' => $inProgressCount,
                'description' => 'In progress & on hold · '.$periodLabel,
                'sparkline' => $dailyCounts('created_at', $pendingReviewStatuses),
                'sparklineValueSuffix' => '',
                'stroke' => '#f97316',
            ],
            [
                'title' => 'Resolved Incidents',
                'value' => $resolvedCount,
                'description' => 'Resolved in '.$periodLabel,
                'sparkline' => $dailyCounts('resolved_at', $resolvedForStatWidgets),
                'sparklineValueSuffix' => '',
                'stroke' => '#3b82f6',
            ],
            [
                'title' => 'Avg. Resolution Time',
                'value' => $avgHours > 0 ? round($avgHours, 1).'h' : '—',
                'description' => 'Mean hours (resolved tickets) · '.$periodLabel,
                'sparkline' => $avgTimeSparkline,
                'sparklineValueSuffix' => 'h',
                'stroke' => '#10b981',
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

        // ── Priority distribution (admin-configured priorities) ───────
        $prioritiesConfigured = TicketPriority::orderBy('sort_order')->get(['name', 'color', 'icon']);

        $priorityRaw = Ticket::selectRaw('priority, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('priority')
            ->pluck('c', 'priority');

        $priorityLegend = $prioritiesConfigured->map(fn ($p) => [
            'name' => $p->name,
            'hex' => $this->normalizeChartHex($p->color),
            'icon' => $p->icon,
        ])->values()->all();

        $configuredPriorityNames = $prioritiesConfigured->pluck('name')->all();

        $severities = $prioritiesConfigured->map(function ($p) use ($priorityRaw) {
            return [
                'name' => $p->name,
                'count' => (int) $priorityRaw->get($p->name, 0),
                'color' => 'bg-gray-500',
                'hex' => $this->normalizeChartHex($p->color),
            ];
        })->values()->all();

        $orphanPriorities = $priorityRaw->filter(
            fn ($count, $name) => ! in_array($name, $configuredPriorityNames, true) && (int) $count > 0
        );

        foreach ($orphanPriorities as $name => $count) {
            $severities[] = [
                'name' => $name,
                'count' => (int) $count,
                'color' => 'bg-gray-500',
                'hex' => '#6b7280',
            ];
        }

        // ── Category distribution (admin-configured categories) ─────
        $categoriesConfigured = TicketCategory::orderedTreeForSettings()->map(fn (TicketCategory $c): object => (object) [
            'name' => $c->name,
        ]);

        $categoryPalette = ['#3b82f6', '#a855f7', '#f97316', '#22c55e', '#ef4444', '#06b6d4', '#eab308', '#ec4899', '#6366f1', '#14b8a6', '#84cc16', '#f59e0b'];

        $categoryRaw = Ticket::selectRaw('category, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('category')
            ->pluck('c', 'category');

        $categoryLegend = $categoriesConfigured->values()
            ->map(fn ($cat, $i) => [
                'name' => $cat->name,
                'hex' => $categoryPalette[$i % count($categoryPalette)],
            ])->values()->all();

        $configuredCategoryNames = $categoriesConfigured->pluck('name')->all();

        $categories = $categoriesConfigured->values()
            ->map(function ($cat, $i) use ($categoryRaw, $categoryPalette) {
                $count = (int) $categoryRaw->get($cat->name, 0);
                if ($count === 0) {
                    return null;
                }

                return [
                    'name' => $cat->name,
                    'count' => $count,
                    'color' => 'bg-gray-500',
                    'hex' => $categoryPalette[$i % count($categoryPalette)],
                ];
            })
            ->filter()
            ->values()
            ->all();

        $orphanCategories = $categoryRaw->filter(
            fn ($count, $name) => ! in_array($name, $configuredCategoryNames, true) && (int) $count > 0
        );

        foreach ($orphanCategories as $name => $count) {
            $categories[] = [
                'name' => $name,
                'count' => (int) $count,
                'color' => 'bg-gray-500',
                'hex' => '#6b7280',
            ];
        }

        // ── Top recurring incidents (tag leaderboard: tickets in period) ──
        $topRecurring = Tag::query()
            ->join('tag_ticket', 'tags.id', '=', 'tag_ticket.tag_id')
            ->join('tickets', 'tag_ticket.ticket_id', '=', 'tickets.id')
            ->where('tickets.created_at', '>=', $currentPeriodStart)
            ->where('tickets.created_at', '<=', $now)
            ->groupBy('tags.id', 'tags.name')
            ->selectRaw('tags.name as name, COUNT(tag_ticket.ticket_id) as total')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row, $i): array => [
                'rank' => $i + 1,
                'tag' => $row->name,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        // ── Recent activity ───────────────────────────────────────────
        $recentActivity = Ticket::with(['reporter', 'handlers', 'tags'])
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
                'tags' => $ticket->tags->pluck('name')->toArray(),
                'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
                'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
            ])->all();

        $recentComments = TicketComment::with(['user', 'ticket.reporter', 'ticket.handlers', 'ticket.tags'])
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
                    'ticketTags' => $ticket?->tags?->pluck('name')->toArray() ?? [],
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
            'severities' => $severities,
            'categories' => $categories,
            'priorityLegend' => $priorityLegend,
            'categoryLegend' => $categoryLegend,
            'topRecurring' => $topRecurring,
            'recentActivity' => $recentActivity,
            'recentComments' => $recentComments,
            '_window' => ['start' => $currentPeriodStart, 'end' => $now],
        ];
    }

    private function normalizeChartHex(?string $hex, string $fallback = '#6b7280'): string
    {
        $hex = trim((string) $hex);
        if ($hex === '') {
            return $fallback;
        }
        if (! str_starts_with($hex, '#')) {
            $hex = '#'.$hex;
        }
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $h = $m[1];

            return strtolower('#'.$h[0].$h[0].$h[1].$h[1].$h[2].$h[2]);
        }
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return strtolower($hex);
    }
}
