<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the admin settings page.
     */
    public function index(): Response
    {
        $settings = Setting::all()
            ->groupBy('group')
            ->map(fn ($group) => $group->keyBy('key')->map(fn ($s) => [
                'key' => $s->key,
                'value' => $s->value,
                'type' => $s->type,
            ]));

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'categories' => TicketCategory::orderBy('sort_order')->get(['id', 'name', 'icon']),
            'priorities' => TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            'statuses' => TicketStatus::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * Persist updated general/appearance settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['present', 'nullable'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            if ($setting->type === 'json' && is_array($value)) {
                $setting->update(['value' => json_encode($value)]);
            } else {
                $setting->update(['value' => $value]);
            }
        }

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }

    /**
     * Bulk-replace ticket categories.
     */
    public function updateCategories(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.icon' => ['required', 'string', 'max:100'],
        ]);

        TicketCategory::truncate();

        foreach ($validated['categories'] as $index => $row) {
            TicketCategory::create([
                'name' => $row['name'],
                'icon' => $row['icon'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Categories saved successfully.');
    }

    /**
     * Bulk-replace ticket priorities.
     */
    public function updatePriorities(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'priorities' => ['required', 'array'],
            'priorities.*.name' => ['required', 'string', 'max:100'],
            'priorities.*.icon' => ['required', 'string', 'max:100'],
            'priorities.*.color' => ['required', 'string', 'max:20'],
        ]);

        TicketPriority::truncate();

        foreach ($validated['priorities'] as $index => $row) {
            TicketPriority::create([
                'name' => $row['name'],
                'icon' => $row['icon'],
                'color' => $row['color'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Priorities saved successfully.');
    }

    /**
     * Bulk-replace ticket statuses.
     */
    public function updateStatuses(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*.name' => ['required', 'string', 'max:100'],
        ]);

        TicketStatus::truncate();

        foreach ($validated['statuses'] as $index => $row) {
            TicketStatus::create([
                'name' => $row['name'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Statuses saved successfully.');
    }
}
