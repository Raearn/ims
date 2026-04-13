---
name: Dashboard widget UI depth
overview: "Redesign the six non-stat dashboard panels in [Dashboard.vue](resources/js/pages/Dashboard.vue) with a consistent “rich depth” pattern: gradient header bands, clearer separation, and elevated hover states—while leaving the four stat cards (the `stats` grid) unchanged."
todos:
  - id: define-classes
    content: Add shared Tailwind class strings (or cn helpers) for non-stat card shell + header band + content well in Dashboard.vue
    status: completed
  - id: apply-six-widgets
    content: Apply pattern to Recent Open, Over Time, Severity, Category, Top Recurring, Recent Comments; keep stat grid markup untouched
    status: completed
  - id: run-dashboard-test
    content: Run tests/Feature/DashboardTest.php and fix any accidental breakage
    status: completed
isProject: false
---

# Admin dashboard: non-stat widget UI (rich depth)

## Scope

- **In scope:** All `Card` blocks in the main dashboard grid **below** the stat row in [resources/js/pages/Dashboard.vue](resources/js/pages/Dashboard.vue):
  1. Recent Open Incidents (~818–913)
  2. Incidents Over Time (~916–973)
  3. Incidents by Severity (~981–1023)
  4. Incidents by Category (~1026–1122)
  5. Top Recurring Incidents (~1127–1194) — use as the **reference** for header treatment; align siblings to it
  6. Recent Comments (~1197–1304)
- **Out of scope:** The `v-for="(stat, idx) in stats"` block (~766–812), dialogs/modals, and backend ([DashboardController.php](app/Http/Controllers/DashboardController.php)).

## Design system (consistent across the six widgets)

Apply one repeated pattern so the page feels intentional rather than six one-off styles:

1. **Card shell** — Keep using shadcn `Card` ([Card.vue](resources/js/components/ui/card/Card.vue) already provides `rounded-xl border bg-card shadow`). Layer **rich depth** via the card’s `class`: e.g. `overflow-hidden`, `transition-all duration-300`, `hover:shadow-lg` (or similar), and a very subtle **ring** only if it still reads well in dark mode (prefer theme tokens: `border-border`, `ring-border/20`).

2. **Header band** — Match the **Top Recurring** idea: `CardHeader` with `border-b border-border/50`, `bg-gradient-to-b from-muted/30 to-transparent`, comfortable vertical padding (`pt-5 pb-4` or equivalent). Inside: title + description on the left; **toolbar** (refresh, chart toggle, badges) on the right, vertically aligned.

3. **Optional semantic markup** — Where you currently use a raw `<p class="text-sm text-muted-foreground">` under the title, consider swapping to `CardDescription` from `@/components/ui/card` for consistency with shadcn patterns (same look, clearer structure).

4. **Content wells** — For charts and dense lists, wrap the main body in an inner region: `rounded-xl bg-muted/15` (or `/20`) + `border border-border/40` + padding, so the chart/list sits **inset** and the header band reads as a clear cap. **Incidents Over Time** especially benefits from this (the `VisXYContainer` area ~953–971).

5. **List / row depth** — For Recent Open, Top Recurring rows, and Recent Comments items: unify hover (`hover:bg-muted/30` or gradient hover), optional **very subtle** `shadow-sm` on rows or parent well—avoid competing with the card-level hover.

6. **Severity bar/donut toggle** — Keep behavior identical; restyle the segmented control to sit cleanly in the header band (same height as other header actions).

## Implementation approach

- Implement entirely in [resources/js/pages/Dashboard.vue](resources/js/pages/Dashboard.vue) template (and minimal script only if you introduce shared `const dashboardPanelCard = cn(...)` / header classes to avoid copy-paste drift).
- **Do not** change the default `Card` primitive unless the whole app should change—scope stays on this page.
- After edits, run [tests/Feature/DashboardTest.php](tests/Feature/DashboardTest.php) (Inertia prop assertions; UI-only changes should still pass).

## Verification

- `php artisan test --compact tests/Feature/DashboardTest.php`
- Manual check in browser (light/dark): stat row unchanged; six panels share header band + inset content; charts remain readable; no layout regressions at `sm` / `lg` breakpoints.
