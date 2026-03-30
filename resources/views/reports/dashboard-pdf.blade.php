<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Incident Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1e293b;
        }
        .header p {
            margin: 5px 0 0;
            color: #64748b;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
            margin-bottom: 15px;
            color: #0f172a;
        }
        /* Grid for KPI */
        .kpi-grid {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .kpi-grid td {
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .kpi-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            display: block;
        }
        .kpi-label {
            font-size: 11px;
            color: #475569;
            text-transform: uppercase;
            margin-top: 5px;
            display: block;
        }
        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
        }
        /* Charts */
        .charts-container {
            width: 100%;
            text-align: center;
        }
        .chart-half {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            text-align: center;
        }
        .chart-full {
            width: 100%;
            text-align: center;
            margin-top: 20px;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Incident Management Dashboard Report</h1>
        <p>Period: {{ $startDate->format('M d, Y') }} - {{ now()->format('M d, Y') }} (Last {{ $days }} Days)</p>
        <p>Generated on: {{ now()->format('M d, Y H:i:s') }}</p>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <div class="section-title">Executive Summary</div>
        <table class="kpi-grid">
            <tr>
                <td>
                    <span class="kpi-value">{{ $totalTickets }}</span>
                    <span class="kpi-label">Tickets Created</span>
                </td>
                <td>
                    <span class="kpi-value">{{ $resolvedTickets }}</span>
                    <span class="kpi-label">Tickets Resolved</span>
                </td>
                <td>
                    <span class="kpi-value">{{ number_format($mttrHours, 1) }}h</span>
                    <span class="kpi-label">Mean Time To Resolve</span>
                </td>
                <td>
                    <span class="kpi-value">{{ $backlog }}</span>
                    <span class="kpi-label">Current Backlog</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Trend Analysis -->
    <div class="section">
        <div class="section-title">Incident Trends</div>
        <div class="charts-container">
            @if($charts['trend'])
                <div class="chart-full">
                    <img src="{{ $charts['trend'] }}" alt="Trend Chart">
                </div>
            @else
                <p>No trend data available.</p>
            @endif
        </div>
    </div>

    <div class="page-break"></div>

    <!-- Breakdowns -->
    <div class="section">
        <div class="section-title">Categorization & Priorities</div>
        <div class="charts-container">
            <div class="chart-half">
                @if($charts['priority'])
                    <img src="{{ $charts['priority'] }}" alt="Priority Chart">
                @endif
            </div>
            <div class="chart-half">
                @if($charts['category'])
                    <img src="{{ $charts['category'] }}" alt="Category Chart">
                @endif
            </div>
        </div>
    </div>

    <!-- Team Workload -->
    <div class="section">
        <div class="section-title">Team Workload & Performance</div>
        @if($workload->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Currently Open/In Progress</th>
                        <th>Resolved (Period)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workload as $agent)
                        <tr>
                            <td>{{ $agent->name }}</td>
                            <td>{{ $agent->open_tickets_count }}</td>
                            <td>{{ $agent->resolved_tickets_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No agents assigned to tickets in this period.</p>
        @endif
    </div>

    <!-- Action Items -->
    <div class="section">
        <div class="section-title">Action Items: Needs Attention</div>
        
        <h4>Top 5 Oldest Open Tickets (Stale)</h4>
        @if($staleTickets->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staleTickets as $ticket)
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $ticket->status }}</td>
                            <td>{{ $ticket->priority }}</td>
                            <td>{{ $ticket->created_at->format('M d, Y H:i') }} ({{ $ticket->created_at->diffForHumans() }})</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No open tickets currently.</p>
        @endif

        <h4>Critical Priority Tickets (Last {{ $days }} Days)</h4>
        @if($criticalTickets->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($criticalTickets as $ticket)
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $ticket->status }}</td>
                            <td>{{ $ticket->category }}</td>
                            <td>{{ $ticket->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No critical tickets reported in this period.</p>
        @endif
    </div>

</body>
</html>