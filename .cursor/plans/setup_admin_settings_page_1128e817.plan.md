---
name: Setup Admin Settings Page
overview: Create a database-backed settings page in the admin dashboard to manage application configuration and helpdesk parameters dynamically.
todos:
  - id: create-model-migration
    content: Create Setting model, migration, and SettingsSeeder
    status: pending
  - id: create-settings-helper
    content: Create SettingsHelper to retrieve and cache settings
    status: pending
  - id: create-controller
    content: Create Admin/SettingsController with index and update methods
    status: pending
  - id: register-routes
    content: Register /admin/settings routes in routes/web.php
    status: pending
  - id: create-frontend-page
    content: Create Admin/Settings/Index.vue frontend page with shadcn tabs
    status: pending
  - id: update-sidebar
    content: Add Settings link to AppSidebar.vue
    status: pending
  - id: refactor-hardcoded-maps
    content: Refactor hardcoded priority and category maps to use the new settings
    status: pending
isProject: false
---

# Setup Admin Settings Page

This plan details the steps to build a dynamic Settings page for the admin dashboard, storing the values in a new `settings` table rather than config files. 

## 1. Database & Model

- Create a `Setting` model and migration using `php artisan make:model Setting -m`.
- The migration will include fields: `key` (string, unique index), `value` (json or text), and `type` (string, to identify if it's a string, boolean, array, etc.).
- Create a `SettingsSeeder` to populate default keys, matching the hardcoded data currently in the app:
  - Application settings: `app_name`, `support_email`
  - Helpdesk settings: `ticket_categories`, `ticket_priorities` (with their colors/hex codes), `sla_resolution_hours`

## 2. Global Settings Helper

- Add a helper method/class (e.g. `SettingsHelper` or cache-based helper) to easily retrieve settings throughout the application (both controllers and Vue).
- Update `App\Http\Middleware\HandleInertiaRequests.php` to share public settings (like app name or categories) globally with the Inertia frontend if necessary.

## 3. Backend Implementation

- Create `App\Http\Controllers\Admin\SettingsController`.
- `**index()**`: Fetches all settings, formats them, and returns an Inertia view `Admin/Settings/Index`.
- `**update()**`: Validates the incoming key-value pairs and updates the `settings` table. It will clear any cached settings.
- Add routes in `routes/web.php` within the `['auth', 'verified', 'role:admin']` middleware group.

## 4. Frontend Views

- Create `resources/js/pages/Admin/Settings/Index.vue`.
- Utilize `shadcn-vue` components (Tabs, Cards, Inputs, Selects) to organize the page into two distinct tabs:
  - **Application Configuration:** Form for general app settings (Name, Contact Email).
  - **Helpdesk Parameters:** Form for adjusting SLA hours, and managing categories/priorities (with their colors and icons from lucide).
- Use Inertia's `useForm` to handle submissions seamlessly.

## 5. Sidebar Navigation Integration

- Edit `resources/js/components/AppSidebar.vue`.
- Add a new `SidebarMenuItem` under the "Administration" group for "Settings" with an appropriate Lucide icon (e.g., `Settings` or `Sliders`).

## 6. Refactoring Hardcoded Values (Optional but recommended)

- Refactor the hardcoded `$priorityMap` and `$categoryMap` inside `routes/web.php` (for the Dashboard view) to pull dynamically from the new `Setting` model instead.

