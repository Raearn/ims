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

        $categoryDisplayPaths = TicketCategory::displayPathLookupFromSettings();

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

        $avgSeconds = (int) (Ticket::whereNotNull('resolved_at')
            ->whereIn('status', $resolvedForStatWidgets)
            ->where('resolved_at', '>=', $currentPeriodStart)
            ->where('resolved_at', '<=', $now)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) as s')
            ->value('s') ?? 0);

        $formattedAvgTime = '—';
        if ($avgSeconds > 0) {
            $h = floor($avgSeconds / 3600);
            $m = floor(($avgSeconds % 3600) / 60);
            $s = $avgSeconds % 60;
            $formattedAvgTime = sprintf('%02d:%02d:%02d', $h, $m, $s);
        }

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
                'stroke' => '#10b981',
            ],
            [
                'title' => 'Avg. Resolution Time',
                'value' => $formattedAvgTime,
                'description' => 'Mean time (resolved tickets) · '.$periodLabel,
                'sparkline' => $avgTimeSparkline,
                'sparklineValueSuffix' => 'h',
                'stroke' => '#3b82f6',
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

        // ── Category distribution (grouped by top-level category + subcategories) ─────
        $categoryPalette = ['#3b82f6', '#a855f7', '#f97316', '#22c55e', '#ef4444', '#06b6d4', '#eab308', '#ec4899', '#6366f1', '#14b8a6', '#84cc16', '#f59e0b'];

        $categoryRaw = Ticket::selectRaw('category, COUNT(*) as c')
            ->where('created_at', '>=', $currentPeriodStart)
            ->where('created_at', '<=', $now)
            ->groupBy('category')
            ->pluck('c', 'category');

        $roots = TicketCategory::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $configuredCategoryNames = [];
        foreach ($roots as $root) {
            $configuredCategoryNames[] = $root->name;
            foreach ($root->children as $child) {
                $configuredCategoryNames[] = $child->name;
            }
        }

        $categoryChartGroups = [];
        $categoryLegend = [];
        $rootPaletteIndex = 0;

        foreach ($roots as $root) {
            $rootHex = $categoryPalette[$rootPaletteIndex % count($categoryPalette)];
            $rootPaletteIndex++;

            $rootCount = (int) $categoryRaw->get($root->name, 0);
            $children = [];
            foreach ($root->children as $child) {
                $childCount = (int) $categoryRaw->get($child->name, 0);
                if ($childCount > 0) {
                    $children[] = [
                        'name' => $child->name,
                        'count' => $childCount,
                    ];
                }
            }

            $total = $rootCount + array_sum(array_column($children, 'count'));
            if ($total === 0) {
                continue;
            }

            $categoryLegend[] = [
                'name' => $root->name,
                'hex' => $rootHex,
            ];
            foreach ($children as $childRow) {
                $categoryLegend[] = [
                    'name' => $childRow['name'],
                    'hex' => $rootHex,
                ];
            }

            $categoryChartGroups[] = [
                'id' => $root->id,
                'name' => $root->name,
                'hex' => $rootHex,
                'rootCount' => $rootCount,
                'total' => $total,
                'children' => $children,
            ];
        }

        $orphanCategories = $categoryRaw->filter(
            fn ($count, $name) => ! in_array($name, $configuredCategoryNames, true) && (int) $count > 0
        );

        foreach ($orphanCategories as $name => $count) {
            $categoryChartGroups[] = [
                'id' => null,
                'name' => $name,
                'hex' => '#6b7280',
                'rootCount' => (int) $count,
                'total' => (int) $count,
                'children' => [],
            ];
            $categoryLegend[] = [
                'name' => $name,
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
                'incidentOccurredAtFormatted' => $ticket->incident_occurred_at?->format('M d, Y \a\t h:i A'),
                'reporter' => $ticket->reporter?->name ?? 'Unknown',
                'reporterId' => $ticket->user_id,
                'priority' => $ticket->priority,
                'category' => TicketCategory::displayLabelForTicket(
                    $ticket->ticket_category_id,
                    $ticket->category,
                    $categoryDisplayPaths,
                ),
                'tags' => $ticket->tags->pluck('name')->toArray(),
                'handlerIds' => $ticket->handlers->pluck('id')->toArray(),
                'handlers' => $ticket->handlers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray(),
                'attachmentUrl' => $ticket->attachment ? Storage::disk('public')->url($ticket->attachment) : null,
            ])->all();

        $recentComments = TicketComment::with(['user', 'ticket.reporter', 'ticket.handlers', 'ticket.tags'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($comment) use ($categoryDisplayPaths) {
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
                    'snippetImageUrls' => $this->extractCommentSnippetImageUrls((string) $comment->body),
                    'ticketNumericId' => $ticket->id ?? null,
                    'ticketTktId' => 'TKT-'.(1000 + ($ticket->id ?? 0)),
                    'ticketTitle' => $ticket->title ?? '',
                    'ticketDescription' => $ticket->description ?? null,
                    'ticketStatus' => $ticket->status ?? '',
                    'ticketPriority' => $ticket->priority ?? '',
                    'ticketCategory' => $ticket
                        ? TicketCategory::displayLabelForTicket(
                            $ticket->ticket_category_id,
                            $ticket->category ?? '',
                            $categoryDisplayPaths,
                        )
                        : '',
                    'ticketTags' => $ticket?->tags?->pluck('name')->toArray() ?? [],
                    'ticketReporter' => $ticket->reporter?->name ?? 'Unknown',
                    'ticketReporterId' => $ticket->user_id ?? null,
                    'ticketCreatedAtFormatted' => $ticket?->created_at?->format('M d, Y \a\t h:i A') ?? '',
                    'ticketIncidentOccurredAtFormatted' => $ticket?->incident_occurred_at?->format('M d, Y \a\t h:i A'),
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
            'categoryChartGroups' => $categoryChartGroups,
            'priorityLegend' => $priorityLegend,
            'categoryLegend' => $categoryLegend,
            'topRecurring' => $topRecurring,
            'recentActivity' => $recentActivity,
            'recentComments' => $recentComments,
            '_window' => ['start' => $currentPeriodStart, 'end' => $now],
        ];
    }

    /**
     * Pull safe display URLs from img src attributes in stored comment HTML (TipTap).
     *
     * @return list<string>
     */
    private function extractCommentSnippetImageUrls(string $html, int $limit = 4): array
    {
        if ($html === '' || ! preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $urls = [];
        foreach ($matches[1] as $rawSrc) {
            $src = trim(html_entity_decode($rawSrc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($src === '') {
                continue;
            }
            if (preg_match('#^(javascript:|data:)#i', $src)) {
                continue;
            }
            if (str_starts_with($src, '//')) {
                $src = 'https:'.$src;
            }
            if (str_starts_with($src, '/')) {
                $resolved = url($src);
            } elseif (preg_match('#^https?://#i', $src)) {
                $resolved = $src;
            } else {
                continue;
            }
            if (in_array($resolved, $urls, true)) {
                continue;
            }
            $urls[] = $resolved;
            if (count($urls) >= $limit) {
                break;
            }
        }

        return $urls;
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
