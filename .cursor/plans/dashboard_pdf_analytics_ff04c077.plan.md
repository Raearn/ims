---
name: Dashboard_PDF_Analytics
overview: Implement a comprehensive, manager-focused PDF report export for the dashboard featuring executive summaries, workload distribution, trend analysis, and critical actionable items based on the existing ticket schema.
todos:
  - id: pdf-package-setup
    content: Install and configure a Laravel PDF generation package.
    status: pending
  - id: create-report-service
    content: Create ReportGenerationService to aggregate Ticket, User, and Activity data into managerial metrics.
    status: pending
  - id: design-pdf-template
    content: Design a print-friendly Blade/HTML template for the PDF layout.
    status: pending
  - id: implement-pdf-charts
    content: Implement a charting solution compatible with PDF generation (e.g., server-side SVGs or Base64 images).
    status: pending
  - id: add-export-feature
    content: Add the Export controller method, route, and frontend 'Download PDF' button.
    status: pending
isProject: false
---

# Dashboard PDF Export Analytics Implementation Plan

## 1. Setup PDF Generation Tooling
- Install a PDF generation package (e.g., `barryvdh/laravel-dompdf` or `spatie/laravel-pdf`).
- Create a dedicated standard Laravel View (Blade template) or Vue component structure specifically styled for print/PDF output (A4 size, page breaks, clean typography).

## 2. Create the Data Aggregation Service
Create a new service `App\Services\ReportGenerationService` to calculate the managerial metrics:
- **Executive Summary:** Count total tickets, calculate MTTR (Mean Time To Resolve) by averaging the time difference between ticket creation and the `TicketActivity` log moving the status to "Resolved".
- **Breakdowns:** Group and count tickets by `status`, `priority`, and `category` using Eloquent queries (`Ticket::groupBy('category')->selectRaw('category, count(*) as total')->get()`).
- **Team Workload:** Join the `tickets` table with the `users` table on `assigned_to` to aggregate ticket counts per agent.
- **Trend Lines:** Group tickets created and resolved by date over the selected period (e.g., last 30 days).

## 3. Generate Charts & Visualizations
- Since PDFs often struggle with dynamic JS charts, use a server-side chart generation strategy.
- Options: Use QuickChart.io (API), generate SVG charts natively in PHP, or pass raw data to a frontend that renders charts via Canvas and attaches them as Base64 images to the PDF generation request.

## 4. Design the PDF Layout Sections
- **Header:** Report title, date range, and generated timestamp.
- **Section 1 (Executive Summary):** 4-column grid of high-level KPI numbers.
- **Section 2 (Categorization):** Visual charts (Priorities, Categories).
- **Section 3 (Team Workload):** Table of agents, assigned tickets, and resolved counts.
- **Section 4 (Action Items):** Table of Top 5 oldest unresolved tickets, and a list of all `Critical` tickets in the period.

## 5. Implement the Controller & Route
- Create a route (e.g., `GET /reports/dashboard/export`).
- Add a controller method to gather data from `ReportGenerationService`, render the View to HTML, convert it to PDF, and return it as a download (`return $pdf->download('incident-report.pdf')`).

## 6. Add UI Export Button
- Update the Dashboard Vue component (`AppLayout` or `Dashboard.vue`) to include an "Export to PDF" button that triggers the download.