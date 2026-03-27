<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Analytics Report — {{ $periodLabel }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 9pt;
        color: #1e293b;
        background: #ffffff;
        line-height: 1.45;
    }

    /* ── Page layout ─────────────────────────────────────────────
       Reserve enough bottom margin so flowing content is not drawn under #footer. -- */
    @page {
        margin: 0mm 0mm 24mm 0mm;
    }

    /* ── Fixed footer on every page ────────────────────────────── */
    #footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 12mm;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 2.5mm 12mm;
        font-size: 7.5pt;
        color: #64748b;
    }
    #footer .footer-left  { float: left;  }
    #footer .footer-right { float: right; }
    #footer .footer-clear { clear: both;  }

    /* ── Cover header band ──────────────────────────────────────── */
    .cover-band {
        background: #0f172a;
        padding: 14mm 12mm 10mm;
        color: #ffffff;
    }
    .cover-band .report-label {
        font-size: 7.5pt;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .cover-band .report-title {
        font-size: 20pt;
        font-weight: 700;
        letter-spacing: -0.3px;
        color: #f1f5f9;
        margin-bottom: 2px;
    }
    .cover-band .report-sub {
        font-size: 9.5pt;
        color: #94a3b8;
    }
    .cover-meta {
        float: right;
        text-align: right;
        font-size: 7.5pt;
        color: #94a3b8;
        padding-top: 6px;
    }
    .cover-meta .meta-period {
        font-size: 11pt;
        font-weight: 700;
        color: #e2e8f0;
        display: block;
        margin-bottom: 2px;
    }

    /* ── Page body wrapper ──────────────────────────────────────── */
    .page-body {
        padding: 8mm 12mm 6mm;
    }

    /* ── Section header ─────────────────────────────────────────── */
    .section-header {
        border-left: 4px solid #3b82f6;
        padding: 3px 0 3px 8px;
        margin: 6mm 0 3mm;
        page-break-after: avoid;
    }
    .section-header.continuation {
        margin-top: 5mm;
    }
    .section-header.break-before {
        page-break-before: always;
        break-before: page;
    }
    .section-header.orange { border-color: #f97316; }
    .section-header.rose   { border-color: #f43f5e; }
    .section-header.emerald{ border-color: #10b981; }
    .section-header.purple { border-color: #a855f7; }
    .section-header.slate  { border-color: #64748b; }

    .section-title {
        font-size: 11pt;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.2px;
    }
    .section-subtitle {
        font-size: 7.5pt;
        color: #64748b;
        margin-top: 1px;
    }

    /* ── KPI cards row ──────────────────────────────────────────── */
    .kpi-row {
        width: 100%;
        border-collapse: separate;
        border-spacing: 5px 0;
        margin-bottom: 5mm;
    }
    .kpi-card {
        width: 33.3%;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 10px;
        vertical-align: top;
        background: #f8fafc;
    }
    .kpi-card.rose    { border-color: #fecdd3; background: #fff1f2; }
    .kpi-card.orange  { border-color: #fed7aa; background: #fff7ed; }
    .kpi-card.blue    { border-color: #bfdbfe; background: #eff6ff; }

    .kpi-label {
        font-size: 7pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .kpi-label.rose   { color: #be123c; }
    .kpi-label.orange { color: #c2410c; }
    .kpi-label.blue   { color: #1d4ed8; }

    .kpi-value {
        font-size: 22pt;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
        margin-bottom: 4px;
    }
    .kpi-value.rose   { color: #e11d48; }
    .kpi-value.orange { color: #ea580c; }
    .kpi-value.blue   { color: #2563eb; }

    .kpi-trend {
        font-size: 7.5pt;
        padding: 1px 5px;
        border-radius: 10px;
        display: inline-block;
        font-weight: 600;
    }
    .kpi-trend.up   { background: #dcfce7; color: #15803d; }
    .kpi-trend.down { background: #fee2e2; color: #b91c1c; }
    .kpi-trend.neutral { background: #f1f5f9; color: #475569; }

    .kpi-comparison {
        font-size: 6.5pt;
        color: #94a3b8;
        margin-top: 3px;
    }

    /* ── Avg resolution footnote ────────────────────────────────── */
    .avg-row {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 5px;
        padding: 5px 10px;
        margin-bottom: 4mm;
        font-size: 8pt;
        color: #166534;
    }
    .avg-row strong { font-weight: 700; font-size: 10pt; }

    /* ── Data tables ────────────────────────────────────────────── */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-bottom: 4mm;
    }
    .data-table thead tr {
        background: #1e293b;
        color: #f1f5f9;
    }
    .data-table thead th {
        padding: 5px 7px;
        text-align: left;
        font-size: 7pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .data-table thead th.right { text-align: right; }
    .data-table thead th.center { text-align: center; }

    .data-table tbody tr:nth-child(odd)  { background: #f8fafc; }
    .data-table tbody tr:nth-child(even) { background: #ffffff; }
    .data-table tbody tr:hover           { background: #f1f5f9; }

    .data-table tbody td {
        padding: 4px 7px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .data-table tbody td.right  { text-align: right; }
    .data-table tbody td.center { text-align: center; }
    .data-table tbody td.muted  { color: #94a3b8; }
    .data-table tbody td.bold   { font-weight: 700; }

    /* ── Priority / Category group headers ──────────────────────── */
    .group-header {
        padding: 5px 8px;
        font-size: 8.5pt;
        font-weight: 700;
        color: #ffffff;
        margin-top: 4mm;
        margin-bottom: 1mm;
        border-radius: 4px;
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    .group-header.critical  { background: #e11d48; }
    .group-header.high      { background: #ea580c; }
    .group-header.medium    { background: #ca8a04; }
    .group-header.low       { background: #2563eb; }
    .group-header.category  { background: #334155; }

    .group-summary {
        font-size: 7pt;
        font-weight: 400;
        color: rgba(255,255,255,0.8);
        margin-left: 6px;
    }

    /* ── Distribution summary bar ───────────────────────────────── */
    .dist-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4mm;
        font-size: 8pt;
    }
    .dist-table td { padding: 3px 6px; vertical-align: middle; }
    .dist-bar-wrap {
        width: 140px;
        background: #e2e8f0;
        border-radius: 3px;
        height: 8px;
        overflow: hidden;
        display: inline-block;
    }
    .dist-bar-fill {
        height: 8px;
        border-radius: 3px;
        display: inline-block;
    }

    /* ── Trend badge (recurring) ────────────────────────────────── */
    .badge-up   { background: #dcfce7; color: #15803d; padding: 1px 5px; border-radius: 10px; font-size: 7pt; font-weight: 600; }
    .badge-down { background: #fee2e2; color: #b91c1c; padding: 1px 5px; border-radius: 10px; font-size: 7pt; font-weight: 600; }

    /* ── Pagination: flow naturally; split long tables across pages ── */
    .data-table thead {
        display: table-header-group;
    }
    .data-table tbody {
        display: table-row-group;
    }
    .priority-block,
    .category-block {
        page-break-inside: auto;
    }

    /* ── Clearfix ────────────────────────────────────────────────── */
    .cf:after { content: ''; display: table; clear: both; }
</style>
</head>
<body>

{{-- ════ Fixed footer ════ --}}
<div id="footer">
    <span class="footer-left">IMS Analytics Report &mdash; {{ $periodLabel }}</span>
    <span class="footer-right">Generated {{ $generatedAt }} &nbsp;|&nbsp; Page <script type="text/php">if (isset($pdf)) { $pdf->page_script('$fontMetrics = $pdf->getFontMetrics(); $font = $fontMetrics->getFont("Arial", "normal"); $pdf->page_text(530, 818, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 8, array(100/255, 116/255, 139/255)); '); }</script></span>
    <div class="footer-clear"></div>
</div>

{{-- ════ Cover band ════ --}}
<div class="cover-band cf">
    <div style="float:left;">
        <div class="report-label">Incident Management System</div>
        <div class="report-title">Analytics Report</div>
        <div class="report-sub">Comprehensive incident summary and performance metrics</div>
    </div>
    <div class="cover-meta">
        <span class="meta-period">{{ $periodLabel }}</span>
        Generated on<br>{{ $generatedAt }}
    </div>
    <div class="footer-clear"></div>
</div>

<div class="page-body">

{{-- ════ Section 1: KPI Metrics ════ --}}
<div class="section-header">
    <div class="section-title">Key Performance Indicators</div>
    <div class="section-subtitle">Core incident metrics for the selected period</div>
</div>

@php
    $openStat     = $stats[0] ?? null;
    $pendingStat  = $stats[1] ?? null;
    $resolvedStat = $stats[2] ?? null;
    $avgStat      = $stats[3] ?? null;

    $trendClass = function($stat) {
        if (!$stat || !($stat['showTrendArrow'] ?? false)) return 'neutral';
        return $stat['isUp'] ? 'up' : 'down';
    };
    $trendDisplay = function($stat) use ($trendClass) {
        if (!$stat) return '—';
        $cls = $trendClass($stat);
        $arrow = '';
        if (($stat['showTrendArrow'] ?? false)) {
            $arrow = $stat['isUp'] ? ' ▲' : ' ▼';
        }
        return $stat['trend'] . $arrow;
    };
@endphp

<table class="kpi-row">
    <tr>
        {{-- Total Open Incidents --}}
        <td class="kpi-card rose">
            <div class="kpi-label rose">Total Open Incidents</div>
            <div class="kpi-value rose">{{ $openStat ? $openStat['value'] : 0 }}</div>
            @if($openStat)
                <span class="kpi-trend {{ $trendClass($openStat) }}">{{ $trendDisplay($openStat) }}</span>
                <div class="kpi-comparison">{{ $openStat['description'] }}</div>
            @endif
        </td>

        {{-- Pending Review --}}
        <td class="kpi-card orange" style="padding-left:5px;">
            <div class="kpi-label orange">Pending Review</div>
            <div class="kpi-value orange">{{ $pendingStat ? $pendingStat['value'] : 0 }}</div>
            @if($pendingStat)
                <span class="kpi-trend {{ $trendClass($pendingStat) }}">{{ $trendDisplay($pendingStat) }}</span>
                <div class="kpi-comparison">{{ $pendingStat['description'] }}</div>
            @endif
        </td>

        {{-- Resolved Incidents --}}
        <td class="kpi-card blue" style="padding-left:5px;">
            <div class="kpi-label blue">Resolved Incidents</div>
            <div class="kpi-value blue">{{ $resolvedStat ? $resolvedStat['value'] : 0 }}</div>
            @if($resolvedStat)
                <span class="kpi-trend {{ $trendClass($resolvedStat) }}">{{ $trendDisplay($resolvedStat) }}</span>
                <div class="kpi-comparison">{{ $resolvedStat['description'] }}</div>
            @endif
        </td>
    </tr>
</table>

@if($avgStat)
<div class="avg-row cf">
    <strong style="float:left; margin-right:8px;">{{ $avgStat['value'] }}</strong>
    <span style="line-height:1.6;">Avg. Resolution Time &mdash; Mean hours to resolve (Resolved tickets only).
    @if($avgStat['showTrendArrow'])
        Trend: <strong>{{ $avgStat['trend'] }} {{ $avgStat['isUp'] ? '▲' : '▼' }}</strong>
    @else
        Trend: <strong>{{ $avgStat['trend'] }}</strong>
    @endif
    &mdash; {{ $avgStat['description'] }}</span>
</div>
@endif

{{-- ════ Section 2: Daily Incident Trend ════ --}}
<div class="section-header orange">
    <div class="section-title">Daily Incident Trend</div>
    <div class="section-subtitle">Created vs. resolved count for each day in the selected window</div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Day</th>
            <th class="right">Created</th>
            <th class="right">Resolved</th>
            <th class="right">Net (Created &minus; Resolved)</th>
        </tr>
    </thead>
    <tbody>
        @php $trendRows = collect($trendData)->filter(fn($r) => $r['created'] > 0 || $r['resolved'] > 0); @endphp
        @forelse($trendRows as $row)
        @php $net = $row['created'] - $row['resolved']; @endphp
        <tr>
            <td class="bold">{{ $row['date'] }}</td>
            <td class="muted">{{ $row['day'] }}</td>
            <td class="right">{{ $row['created'] }}</td>
            <td class="right">{{ $row['resolved'] }}</td>
            <td class="right {{ $net > 0 ? '' : ($net < 0 ? '' : 'muted') }}"
                style="{{ $net > 0 ? 'color:#b91c1c;font-weight:600;' : ($net < 0 ? 'color:#15803d;font-weight:600;' : '') }}">
                {{ $net > 0 ? '+' : '' }}{{ $net }}
            </td>
        </tr>
        @empty
        <tr><td colspan="5" class="muted center" style="padding:10px;">No activity during this period.</td></tr>
        @endforelse
    </tbody>
    @php
        $totalCreated  = collect($trendData)->sum('created');
        $totalResolved = collect($trendData)->sum('resolved');
        $totalNet = $totalCreated - $totalResolved;
    @endphp
    @if($totalCreated > 0 || $totalResolved > 0)
    <tfoot>
        <tr style="background:#1e293b; color:#f1f5f9; font-weight:700;">
            <td colspan="2" style="padding:4px 7px; font-size:7.5pt; text-transform:uppercase; letter-spacing:.5px;">Period Total</td>
            <td class="right" style="padding:4px 7px;">{{ $totalCreated }}</td>
            <td class="right" style="padding:4px 7px;">{{ $totalResolved }}</td>
            <td class="right" style="padding:4px 7px; {{ $totalNet > 0 ? 'color:#fca5a5;' : 'color:#86efac;' }}">
                {{ $totalNet > 0 ? '+' : '' }}{{ $totalNet }}
            </td>
        </tr>
    </tfoot>
    @endif
</table>

<div class="section-header rose continuation break-before">
    <div class="section-title">Priority Distribution</div>
    <div class="section-subtitle">All incidents grouped by severity level with full ticket details</div>
</div>

{{-- Summary bar --}}
@php
    $totalSev = collect($severities)->sum('count');
    $priorityColors = [
        'Critical' => ['header' => 'critical', 'bar' => '#e11d48'],
        'High'     => ['header' => 'high',     'bar' => '#ea580c'],
        'Medium'   => ['header' => 'medium',   'bar' => '#ca8a04'],
        'Low'      => ['header' => 'low',      'bar' => '#2563eb'],
    ];
@endphp

<table class="dist-table">
    <tbody>
        @foreach($severities as $sev)
        @php
            $barColor = $priorityColors[$sev['name']]['bar'] ?? '#64748b';
            $pct = $totalSev > 0 ? round($sev['count'] / $totalSev * 100, 1) : 0;
            $barWidth = $totalSev > 0 ? round($sev['count'] / $totalSev * 140) : 0;
        @endphp
        <tr>
            <td style="width:70px; font-weight:700; color:{{ $barColor }};">{{ $sev['name'] }}</td>
            <td style="width:30px; font-weight:700;">{{ $sev['count'] }}</td>
            <td style="width:160px;">
                <div class="dist-bar-wrap">
                    <div class="dist-bar-fill" style="width:{{ $barWidth }}px; background:{{ $barColor }};"></div>
                </div>
            </td>
            <td class="muted" style="font-size:7pt;">{{ $pct }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Detailed ticket tables per priority --}}
@foreach($priorityOrder as $priority)
    @php
        $tickets = $ticketsByPriority[$priority] ?? [];
        $headerClass = $priorityColors[$priority]['header'] ?? 'category';
    @endphp
    @if(count($tickets) > 0)
    <div class="priority-block">
        <div class="group-header {{ $headerClass }}">
            {{ $priority }} Priority
            <span class="group-summary">&mdash; {{ count($tickets) }} ticket(s)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">TKT ID</th>
                    <th>Title</th>
                    <th style="width:70px;">Category</th>
                    <th style="width:55px;">Status</th>
                    <th style="width:80px;">Reporter</th>
                    <th style="width:90px;">Assigned To</th>
                    <th style="width:65px;">Opened</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $t)
                <tr>
                    <td class="bold muted" style="font-family:monospace;">{{ $t['tktId'] }}</td>
                    <td>{{ $t['title'] }}</td>
                    <td class="muted">{{ $t['category'] }}</td>
                    <td>{{ $t['status'] }}</td>
                    <td>{{ $t['reporter'] }}</td>
                    <td class="muted">{{ $t['handlers'] }}</td>
                    <td class="muted">{{ $t['openedAt'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endforeach

@if(count(array_filter($priorityOrder, fn($p) => count($ticketsByPriority[$p] ?? []) > 0)) === 0)
<p style="color:#94a3b8; font-size:8pt; padding:10px 0;">No tickets recorded in this period.</p>
@endif

<div class="section-header purple continuation">
    <div class="section-title">Category Distribution</div>
    <div class="section-subtitle">All incidents grouped by category with full ticket details</div>
</div>

{{-- Summary bar --}}
@php
    $totalCat = collect($categories)->sum('count');
    $catColorMap = [
        'Network'  => '#3b82f6',
        'Hardware' => '#a855f7',
        'Software' => '#f97316',
        'Access'   => '#22c55e',
        'Security' => '#ef4444',
    ];
@endphp

<table class="dist-table">
    <tbody>
        @foreach($categories as $cat)
        @php
            $barColor = $catColorMap[$cat['name']] ?? '#6b7280';
            $pct = $totalCat > 0 ? round($cat['count'] / $totalCat * 100, 1) : 0;
            $barWidth = $totalCat > 0 ? round($cat['count'] / $totalCat * 140) : 0;
        @endphp
        <tr>
            <td style="width:70px; font-weight:700; color:{{ $barColor }};">{{ $cat['name'] }}</td>
            <td style="width:30px; font-weight:700;">{{ $cat['count'] }}</td>
            <td style="width:160px;">
                <div class="dist-bar-wrap">
                    <div class="dist-bar-fill" style="width:{{ $barWidth }}px; background:{{ $barColor }};"></div>
                </div>
            </td>
            <td class="muted" style="font-size:7pt;">{{ $pct }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Detailed ticket tables per category --}}
@foreach($ticketsByCategory as $categoryName => $tickets)
@php $barColor = $catColorMap[$categoryName] ?? '#6b7280'; @endphp
<div class="category-block">
    <div class="group-header category" style="background:{{ $barColor }};">
        {{ $categoryName }}
        <span class="group-summary">&mdash; {{ count($tickets) }} ticket(s)</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:70px;">TKT ID</th>
                <th>Title</th>
                <th style="width:55px;">Priority</th>
                <th style="width:55px;">Status</th>
                <th style="width:80px;">Reporter</th>
                <th style="width:90px;">Assigned To</th>
                <th style="width:65px;">Opened</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $t)
            @php
                $priorityColor = match($t['priority']) {
                    'Critical' => '#e11d48',
                    'High'     => '#ea580c',
                    'Medium'   => '#ca8a04',
                    'Low'      => '#2563eb',
                    default    => '#64748b',
                };
            @endphp
            <tr>
                <td class="bold muted" style="font-family:monospace;">{{ $t['tktId'] }}</td>
                <td>{{ $t['title'] }}</td>
                <td style="color:{{ $priorityColor }}; font-weight:700;">{{ $t['priority'] }}</td>
                <td>{{ $t['status'] }}</td>
                <td>{{ $t['reporter'] }}</td>
                <td class="muted">{{ $t['handlers'] }}</td>
                <td class="muted">{{ $t['openedAt'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

@if(empty($ticketsByCategory))
<p style="color:#94a3b8; font-size:8pt; padding:10px 0;">No tickets recorded in this period.</p>
@endif

<div class="section-header slate continuation">
    <div class="section-title">Top Recurring Incidents</div>
    <div class="section-subtitle">Most frequently reported issues in the selected period vs. the prior period</div>
</div>

@if(count($topRecurring) > 0)
<table class="data-table">
    <thead>
        <tr>
            <th class="center" style="width:30px;">#</th>
            <th>Incident Title</th>
            <th style="width:70px;">Category</th>
            <th class="right" style="width:70px;">Occurrences</th>
            <th class="center" style="width:90px;">vs Prior Period</th>
        </tr>
    </thead>
    <tbody>
        @foreach($topRecurring as $item)
        <tr>
            <td class="center bold" style="color:#64748b;">{{ $item['rank'] }}</td>
            <td class="bold">{{ $item['title'] }}</td>
            <td class="muted">{{ $item['category'] }}</td>
            <td class="right bold">{{ $item['count'] }}</td>
            <td class="center">
                @if($item['trend'] === 'up')
                    <span class="badge-up">&#9650; +{{ $item['change'] }}%</span>
                @else
                    <span class="badge-down">&#9660; &minus;{{ $item['change'] }}%</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p style="color:#94a3b8; font-size:8pt; padding:10px 0;">No recurring incidents found in this period.</p>
@endif

{{-- ── Report end marker ── --}}
<div style="margin-top:12mm; border-top:2px solid #e2e8f0; padding-top:5mm; text-align:center; color:#94a3b8; font-size:7.5pt;">
    &mdash; End of Report &mdash;<br>
    <span style="font-size:6.5pt;">Generated by Incident Management System &bull; {{ $generatedAt }}</span>
</div>

</div>{{-- /page-body --}}

</body>
</html>
