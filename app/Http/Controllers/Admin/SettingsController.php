<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        $categories = TicketCategory::orderBy('sort_order')->get(['id', 'name', 'icon']);

        $categoryCountsByName = Ticket::query()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->map(fn ($count) => (int) $count);

        $categoryTicketCountsById = $categories->mapWithKeys(
            fn (TicketCategory $row): array => [$row->id => (int) ($categoryCountsByName[$row->name] ?? 0)],
        )->all();

        $priorities = TicketPriority::orderBy('sort_order')->get(['id', 'name', 'icon', 'color']);

        $priorityCountsByName = Ticket::query()
            ->selectRaw('priority, COUNT(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority')
            ->map(fn ($count) => (int) $count);

        $priorityTicketCountsById = $priorities->mapWithKeys(
            fn (TicketPriority $row): array => [$row->id => (int) ($priorityCountsByName[$row->name] ?? 0)],
        )->all();

        $statuses = TicketStatus::orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'handler_requirement']);

        $ticketCountsByName = Ticket::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $statusTicketCountsById = $statuses->mapWithKeys(
            fn (TicketStatus $row): array => [$row->id => (int) ($ticketCountsByName[$row->name] ?? 0)],
        )->all();

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'categories' => $categories,
            'priorities' => $priorities,
            'statuses' => $statuses,
            'categoryTicketCountsById' => $categoryTicketCountsById,
            'priorityTicketCountsById' => $priorityTicketCountsById,
            'statusTicketCountsById' => $statusTicketCountsById,
            'ticketConfigProtectedNames' => [
                'categories' => TicketConfigDefaults::protectedCategoryNames(),
                'priorities' => TicketConfigDefaults::priorityNames(),
                'statuses' => TicketConfigDefaults::statusNames(),
            ],
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

        $this->assertBuiltInCategoryNamesPreserved($validated['categories']);

        $existingNames = TicketCategory::query()->orderBy('sort_order')->pluck('name')->all();
        $newNames = collect($validated['categories'])->pluck('name')->all();
        $removedNames = array_values(array_diff($existingNames, $newNames));

        if ($removedNames !== []) {
            $inUse = collect($removedNames)
                ->filter(fn (string $name) => Ticket::query()->where('category', $name)->exists())
                ->sort()
                ->values()
                ->all();

            if ($inUse !== []) {
                $quoted = array_map(fn (string $n) => '"'.$n.'"', $inUse);
                $list = implode(', ', $quoted);
                $message = count($inUse) === 1
                    ? "Cannot remove category {$list} because one or more tickets still use it. Reassign those tickets first."
                    : "Cannot remove categories {$list} because tickets still use them. Reassign those tickets first.";

                throw ValidationException::withMessages([
                    'categories' => $message,
                ]);
            }
        }

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

        $this->assertBuiltInPriorityNamesPreserved($validated['priorities']);

        $existingNames = TicketPriority::query()->orderBy('sort_order')->pluck('name')->all();
        $newNames = collect($validated['priorities'])->pluck('name')->all();
        $removedNames = array_values(array_diff($existingNames, $newNames));

        if ($removedNames !== []) {
            $inUse = collect($removedNames)
                ->filter(fn (string $name) => Ticket::query()->where('priority', $name)->exists())
                ->sort()
                ->values()
                ->all();

            if ($inUse !== []) {
                $quoted = array_map(fn (string $n) => '"'.$n.'"', $inUse);
                $list = implode(', ', $quoted);
                $message = count($inUse) === 1
                    ? "Cannot remove priority {$list} because one or more tickets still use it. Reassign those tickets first."
                    : "Cannot remove priorities {$list} because tickets still use them. Reassign those tickets first.";

                throw ValidationException::withMessages([
                    'priorities' => $message,
                ]);
            }
        }

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
            'statuses.*.icon' => ['required', 'string', 'max:100'],
            'statuses.*.color' => ['required', 'string', 'max:20'],
            'statuses.*.handler_requirement' => ['required', 'string', 'in:none,optional,required'],
        ]);

        $this->assertBuiltInStatusNamesPreserved($validated['statuses']);
        $this->assertBuiltInStatusHandlerRequirementsMatchDefaults($validated['statuses']);

        $existingNames = TicketStatus::query()->orderBy('sort_order')->pluck('name')->all();
        $newNames = collect($validated['statuses'])->pluck('name')->all();
        $removedNames = array_values(array_diff($existingNames, $newNames));

        if ($removedNames !== []) {
            $inUse = collect($removedNames)
                ->filter(fn (string $name) => Ticket::query()->where('status', $name)->exists())
                ->sort()
                ->values()
                ->all();

            if ($inUse !== []) {
                $quoted = array_map(fn (string $n) => '"'.$n.'"', $inUse);
                $list = implode(', ', $quoted);
                $message = count($inUse) === 1
                    ? "Cannot remove status {$list} because one or more tickets still use it. Reassign those tickets first."
                    : "Cannot remove statuses {$list} because tickets still use them. Reassign those tickets first.";

                throw ValidationException::withMessages([
                    'statuses' => $message,
                ]);
            }
        }

        TicketStatus::truncate();

        foreach ($validated['statuses'] as $index => $row) {
            TicketStatus::create([
                'name' => $row['name'],
                'icon' => $row['icon'],
                'color' => $row['color'],
                'handler_requirement' => $row['handler_requirement'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Statuses saved successfully.');
    }

    /**
     * Permanently delete all tickets that currently use the given workflow status (by DB status name).
     */
    public function destroyTicketsForStatus(TicketStatus $ticketStatus): RedirectResponse
    {
        return $this->destroyTicketsMatching(
            Ticket::query()->where('status', $ticketStatus->name),
            'No tickets used that status.',
            fn (int $n): string => "{$n} ticket(s) with status \"{$ticketStatus->name}\" were deleted. You can remove the status from the list, then save.",
        );
    }

    /**
     * Permanently delete all tickets in the given category (by DB category name).
     */
    public function destroyTicketsForCategory(TicketCategory $ticketCategory): RedirectResponse
    {
        return $this->destroyTicketsMatching(
            Ticket::query()->where('category', $ticketCategory->name),
            'No tickets used that category.',
            fn (int $n): string => "{$n} ticket(s) in category \"{$ticketCategory->name}\" were deleted. You can remove the category from the list, then save.",
        );
    }

    /**
     * Permanently delete all tickets with the given priority (by DB priority name).
     */
    public function destroyTicketsForPriority(TicketPriority $ticketPriority): RedirectResponse
    {
        return $this->destroyTicketsMatching(
            Ticket::query()->where('priority', $ticketPriority->name),
            'No tickets used that priority.',
            fn (int $n): string => "{$n} ticket(s) with priority \"{$ticketPriority->name}\" were deleted. You can remove the priority from the list, then save.",
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInCategoryNamesPreserved(array $rows): void
    {
        $submitted = collect($rows)->pluck('name')->map(fn ($n) => trim((string) $n))->all();
        $missing = array_values(array_diff(TicketConfigDefaults::protectedCategoryNames(), $submitted));
        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'categories' => 'These built-in categories cannot be removed: '
                .collect($missing)->map(fn (string $n) => '"'.$n.'"')->implode(', ').'.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInPriorityNamesPreserved(array $rows): void
    {
        $submitted = collect($rows)->pluck('name')->map(fn ($n) => trim((string) $n))->all();
        $missing = array_values(array_diff(TicketConfigDefaults::priorityNames(), $submitted));
        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'priorities' => 'These built-in priorities cannot be removed: '
                .collect($missing)->map(fn (string $n) => '"'.$n.'"')->implode(', ').'.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInStatusNamesPreserved(array $rows): void
    {
        $submitted = collect($rows)->pluck('name')->map(fn ($n) => trim((string) $n))->all();
        $missing = array_values(array_diff(TicketConfigDefaults::statusNames(), $submitted));
        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'statuses' => 'These built-in statuses cannot be removed: '
                .collect($missing)->map(fn (string $n) => '"'.$n.'"')->implode(', ').'.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInStatusHandlerRequirementsMatchDefaults(array $rows): void
    {
        $defaultsByName = collect(TicketConfigDefaults::statuses())->keyBy('name');

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! $defaultsByName->has($name)) {
                continue;
            }

            $expected = (string) $defaultsByName->get($name)['handler_requirement'];
            $actual = (string) ($row['handler_requirement'] ?? '');

            if ($actual !== $expected) {
                throw ValidationException::withMessages([
                    "statuses.{$index}.handler_requirement" => 'Handler assignment rules for built-in statuses cannot be changed.',
                ]);
            }
        }
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  callable(int): string  $successMessage
     */
    private function destroyTicketsMatching(Builder $query, string $noMatchFlash, callable $successMessage): RedirectResponse
    {
        $tickets = (clone $query)->get(['id', 'title', 'attachment']);

        if ($tickets->isEmpty()) {
            return redirect()->back()->with('success', $noMatchFlash);
        }

        foreach ($tickets as $ticket) {
            if ($ticket->attachment) {
                Storage::disk('public')->delete($ticket->attachment);
            }

            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'ticket_deleted',
                'old_value' => 'TKT-'.(1000 + $ticket->id).': '.$ticket->title,
                'new_value' => null,
                'created_at' => now(),
            ]);
        }

        Ticket::query()->whereIn('id', $tickets->pluck('id'))->delete();

        $n = $tickets->count();

        return redirect()->back()->with('success', $successMessage($n));
    }
}
