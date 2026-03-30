<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ReportGenerationService
{
    public function generateDashboardMetrics($days = 30)
    {
        $startDate = now()->subDays($days);

        $tickets = Ticket::where('created_at', '>=', $startDate)->get();

        // 1. Executive Summary
        $totalTickets = $tickets->count();
        $resolvedTickets = $tickets->where('status', 'Resolved')->count();

        // MTTR (Mean Time to Resolve) in hours
        $resolvedWithDates = $tickets->whereNotNull('resolved_at');
        $mttrHours = $resolvedWithDates->count() > 0
            ? collect($resolvedWithDates)->average(function ($ticket) {
                return $ticket->created_at->diffInHours($ticket->resolved_at);
            })
            : 0;

        // Open tickets backlog (overall backlog, not just from the last X days)
        $backlog = Ticket::whereIn('status', ['Open', 'In Progress'])->count();

        // 2. Breakdowns
        $byPriority = Ticket::where('created_at', '>=', $startDate)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $byCategory = Ticket::where('created_at', '>=', $startDate)
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $byStatus = Ticket::where('created_at', '>=', $startDate)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // 3. Team Workload
        $workload = User::select('users.id', 'users.name')
            ->withCount([
                'handledTickets as open_tickets_count' => function ($query) {
                    $query->whereIn('status', ['Open', 'In Progress']);
                },
                'handledTickets as resolved_tickets_count' => function ($query) use ($startDate) {
                    $query->where('status', 'Resolved')->where('resolved_at', '>=', $startDate);
                },
            ])
            ->having('open_tickets_count', '>', 0)
            ->orHaving('resolved_tickets_count', '>', 0)
            ->orderByDesc('resolved_tickets_count')
            ->get();

        // 4. Trend Lines
        $trendLines = [
            'dates' => [],
            'created' => [],
            'resolved' => [],
        ];

        $period = Carbon::now()->subDays($days)->toPeriod(Carbon::now());

        // Raw queries for grouping by date to avoid N+1 and PHP-side looping for large datasets
        $ticketsCreatedByDate = Ticket::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->pluck('total', 'date');

        $ticketsResolvedByDate = Ticket::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $startDate)
            ->where('status', 'Resolved')
            ->select(DB::raw('DATE(resolved_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->pluck('total', 'date');

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $trendLines['dates'][] = $date->format('M d');
            $trendLines['created'][] = $ticketsCreatedByDate[$dateString] ?? 0;
            $trendLines['resolved'][] = $ticketsResolvedByDate[$dateString] ?? 0;
        }

        // 5. Action Items
        $staleTickets = Ticket::whereIn('status', ['Open', 'In Progress'])
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get();

        $criticalTickets = Ticket::where('created_at', '>=', $startDate)
            ->where('priority', 'Critical')
            ->get();

        // 6. Generate Charts (Base64)
        $charts = [
            'priority' => $this->getChartBase64('pie', array_keys($byPriority), array_values($byPriority), 'Tickets by Priority'),
            'category' => $this->getChartBase64('bar', array_keys($byCategory), array_values($byCategory), 'Tickets by Category'),
            'trend' => $this->getTrendChartBase64($trendLines),
        ];

        return compact(
            'totalTickets',
            'resolvedTickets',
            'mttrHours',
            'backlog',
            'byPriority',
            'byCategory',
            'byStatus',
            'workload',
            'trendLines',
            'staleTickets',
            'criticalTickets',
            'startDate',
            'days',
            'charts'
        );
    }

    private function getChartBase64($type, $labels, $data, $title)
    {
        if (empty($data)) {
            return null;
        }

        $chart = [
            'type' => $type,
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Tickets',
                    'data' => $data,
                    'backgroundColor' => ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#8b5cf6'],
                ]],
            ],
            'options' => [
                'title' => ['display' => true, 'text' => $title],
                'plugins' => ['datalabels' => ['display' => true]],
            ],
        ];

        $url = 'https://quickchart.io/chart?w=400&h=250&bkg=white&c='.urlencode(json_encode($chart));

        try {
            $image = Http::get($url)->body();

            return 'data:image/png;base64,'.base64_encode($image);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getTrendChartBase64($trendLines)
    {
        if (empty($trendLines['dates'])) {
            return null;
        }

        $chart = [
            'type' => 'line',
            'data' => [
                'labels' => $trendLines['dates'],
                'datasets' => [
                    [
                        'label' => 'Created',
                        'data' => $trendLines['created'],
                        'borderColor' => '#ef4444',
                        'fill' => false,
                    ],
                    [
                        'label' => 'Resolved',
                        'data' => $trendLines['resolved'],
                        'borderColor' => '#10b981',
                        'fill' => false,
                    ],
                ],
            ],
            'options' => [
                'title' => ['display' => true, 'text' => 'Ticket Trend'],
            ],
        ];

        $url = 'https://quickchart.io/chart?w=600&h=300&bkg=white&c='.urlencode(json_encode($chart));

        try {
            $image = Http::get($url)->body();

            return 'data:image/png;base64,'.base64_encode($image);
        } catch (\Exception $e) {
            return null;
        }
    }
}
