---
name: Dashboard_Enhancements
overview: Implement global date range filtering for all metrics, add a Quick Actions widget for fast navigation, and build a print-friendly layout for exporting the dashboard to PDF.
todos:
  - id: date-filter-backend
    content: Refactor dashboard route in web.php to support dynamic period filtering
    status: completed
  - id: date-filter-frontend
    content: Add Date Picker UI to Dashboard.vue and wire up Inertia navigation
    status: completed
  - id: quick-actions
    content: Create the Quick Actions widget card in Dashboard.vue
    status: completed
  - id: pdf-export
    content: Implement Print-to-PDF styles and Export button in Dashboard.vue
    status: completed
isProject: false
---

# Admin Dashboard Enhancements

We will implement the three selected features to make the admin dashboard more powerful and user-friendly.

## 1. Global Date Range Filtering

- Update `routes/web.php` (dashboard route) to accept a `period` query parameter (e.g., `7d`, `30d`, `this_month`, `last_month`, `ytd`, `all`).
- Refactor the hardcoded `$thisWeekStart` and `$lastWeekStart` logic in the backend to calculate the appropriate `$currentPeriod` and `$previousPeriod` ranges based on the requested filter.
- Apply these date bounds to **all** queries (stats, sparklines, trend charts, priority/category distributions, top recurring).
- Add a dropdown picker in the header of `Dashboard.vue` (next to the refresh button) to select the date range. When changed, use `router.get` to reload the page with the new `period` parameter.

## 2. Quick Actions Widget

- Add a new "Quick Actions" card to the dashboard grid in `Dashboard.vue`.
- Include distinct, icon-driven buttons linking to common administrative tasks:
  - Add New User (links to Users page or opens a modal if supported)
  - Configure Ticket Settings (links to `admin.settings`)
  - Create System Backup (triggers the `diagnostics.backup` action)
  - View Audit Log (links to `audit-log`)

## 3. Dashboard PDF Export (Print Layout)

- Exporting complex JS-rendered charts (like Unovis) from the backend is notoriously fragile. The most robust approach is a frontend "Print to PDF" layout.
- Add an "Export / Print" button to the dashboard header.
- Add a comprehensive `@media print` section to `Dashboard.vue` (or the global CSS) that:
  - Hides the sidebar, top navigation, modals, and action buttons.
  - Expands the main layout to full width.
  - Formats the stats grid and charts cleanly to fit A4/Letter sizing.
  - Adjusts background colors and borders for clean paper/PDF rendering.
- When the button is clicked, it will simply trigger `window.print()`, allowing the user to seamlessly save the dashboard as a PDF.

