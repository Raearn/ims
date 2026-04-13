---
name: Category widget bar-only
overview: Remove the category donut toggle from the admin Dashboard and reshape backend + UI so "Incidents by Category" is bar-only, grouped by top-level category with a clear parent/child presentation and correct drill-down for whole families.
todos:
  - id: backend-groups
    content: "DashboardController: build categoryChartGroups + legend; adjust/remove flat categories prop; keep totals consistent"
    status: completed
  - id: route-root-peek
    content: Add GET tickets/by-category-root/{ticketCategory} + feature test for period + subtree names/ids
    status: completed
  - id: vue-widget
    content: "Dashboard.vue: remove category donut/toggle; hierarchical bar UI; root vs child modal fetch"
    status: completed
  - id: dashboard-test
    content: Update DashboardTest Inertia assertions for new prop shape
    status: completed
isProject: false
---

# Incidents by Category: bar-only + hierarchical display

## Current behavior

- [Dashboard.vue](resources/js/pages/Dashboard.vue): `categoryChartType` toggles bar vs `DonutChart`; bar mode maps a **flat** `categories` list (name, count, hex).
- [DashboardController.php](app/Http/Controllers/DashboardController.php) (~290–340): builds that list from `TicketCategory::orderedTreeForSettings()` but **only keeps `name`**, then matches counts via `Ticket::groupBy('category')` on the denormalized `category` string.
- [Dashboard.vue](resources/js/pages/Dashboard.vue) `openCategoryModal` calls [tickets.by-category](routes/web.php) with a **single** category name.

Severity widget keeps its bar/donut toggle; **only the category card changes**.

## Backend: grouped payload

In `buildDashboardData()` (same file as today):

1. Keep loading period-scoped counts by name: `$categoryRaw = Ticket::…->groupBy('category')->pluck('c', 'category')` (unchanged source of truth for display numbers).
2. Use **full** `TicketCategory` models from `orderedTreeForSettings()` (already returns roots then children; includes id, name, parent_id).
3. Build **`categoryChartGroups`**: for each **root** with `parent_id === null`:
   - `id`, `name`, `hex` (same palette rotation as today’s legend).
   - `rootCount` = `(int) $categoryRaw->get($root->name, 0)` (tickets stored with parent label only).
   - `children`: each direct child with `count` from `$categoryRaw`; omit zero-count children from the array (or include with count 0 only if you want explicit zeros—default: hide).
   - `total` = `rootCount + sum(child counts)`.
   - **Omit** the whole group when `total === 0`.
4. **Orphans**: names in `$categoryRaw` not in the configured tree (existing logic) become **single-row groups** (e.g. `children: []`, `total` = count) so nothing is lost vs today.
5. **`categoryLegend`**: flatten groups for color lookup (root + each child name → hex); orphans keep `#6b7280` as today.
6. **`categories` prop**: either **remove** and migrate the dashboard to groups only, or keep a **flattened** `categories` derived from groups + orphans solely for any stray consumers—prefer **remove** from Inertia payload once [Dashboard.vue](resources/js/pages/Dashboard.vue) is updated (grep shows only this page uses it for the chart).

Also add **`totalCategoriesCount`** consistency: it should remain the sum of **all** incident counts in the period for categories shown (same as summing non-zero rows today—use sum of group `total` plus orphans, avoiding double-counting names).

## New JSON route for “whole family” drill-down

Add a route next to `tickets.by-category` (same `admin` middleware group), e.g. **`GET tickets/by-category-root/{ticketCategory}`** with `period` validation mirroring existing peek routes.

- Resolve `TicketCategory` (route-model binding or manual).
- Abort unless `$ticketCategory->isRoot()`.
- Collect **subtree category names**: root name + direct children names (matches app’s one-level tree); optionally load children via `$ticketCategory->children` relation.
- Query tickets in the reporting window:
  - `whereIn('ticket_category_id', $ids)` **or** `whereNull('ticket_category_id')->whereIn('category', $names)` for legacy rows without FK (mirror how the app still relies on `category` string elsewhere).

Return the **same JSON shape** as `tickets.by-category` so the existing modal list UI can be reused.

## Frontend: [Dashboard.vue](resources/js/pages/Dashboard.vue)

1. Remove `categoryChartType`, the category bar/donut toggle UI, and the category `DonutChart` branch (keep `DonutChart` + `PieChart` for severity).
2. Extend props with a typed `categoryChartGroups` (and drop `categories` if removed server-side).
3. **Creative layout** (bar-only, hierarchy obvious):
   - For each **group**, render a **contained block** (e.g. rounded border, subtle `border-l-4` using root `hex`).
   - **Header row**: root name, total count, and **one full-width bar** whose width = `total / totalCategoriesCount` (global %, same mental model as today).
   - **Stacked segment strip** inside that bar (optional but strong visually): horizontal flex segments proportional to **child counts + root-only count** (root-only as its own segment when `rootCount > 0`). Use child-specific tints (e.g. CSS `color-mix` / alpha on root hex) so segments read as one family.
   - **Child rows** below: indented (`pl-3`/`ml-2` + faint guide line), smaller label `Parent / Child` or `↳ Child`, per-child bar width = `child.count / totalCategoriesCount`, click opens existing name-based modal for that leaf.
   - **Root row / block click** (not on a child): open a new `openCategoryRootModal(rootId)` that calls the new route and sets modal title to the root name (e.g. `Network (all types)`).

4. Update `categoryHexByName` (or equivalent) to derive from flattened legend from groups.

## Tests

- **Feature test** for `tickets/by-category-root/{id}`: seed root + child categories, tickets on child and/or parent-only string, assert JSON count and membership for a period.
- **DashboardTest** ([tests/Feature/DashboardTest.php](tests/Feature/DashboardTest.php)): assert Inertia has `categoryChartGroups` (and remove `categories` assertion if that prop is removed).

## Out of scope (unless you want it)

- PDF export uses [ReportGenerationService](app/Services/ReportGenerationService.php) / `reports.dashboard-pdf`, not the same Inertia payload—leave unchanged unless you want PDF to match the new grouping.
