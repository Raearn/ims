<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Support\AdminSettingsAuditFormatter;
use App\Support\TicketConfigDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $categories = TicketCategory::orderedTreeForSettings()->map(fn (TicketCategory $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'icon' => $c->icon,
            'parent_id' => $c->parent_id,
        ])->values()->all();

        $countsByCategoryId = Ticket::query()
            ->whereNotNull('ticket_category_id')
            ->selectRaw('ticket_category_id, COUNT(*) as aggregate')
            ->groupBy('ticket_category_id')
            ->pluck('aggregate', 'ticket_category_id')
            ->map(fn ($count) => (int) $count);

        $categoryTicketCountsById = collect($categories)
            ->mapWithKeys(fn (array $row): array => [
                $row['id'] => (int) ($countsByCategoryId[$row['id']] ?? 0),
            ])
            ->all();

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
                'categories' => [],
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

        $changed = [];

        foreach ($validated['settings'] as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $rawOld = $setting->getRawOriginal('value');
            $oldVal = $setting->type === 'json'
                ? (is_string($rawOld) ? $rawOld : json_encode($rawOld))
                : (string) ($rawOld ?? '');

            if ($setting->type === 'json' && is_array($value)) {
                $newVal = json_encode($value);
                if ($oldVal !== $newVal) {
                    $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
                $setting->update(['value' => $value]);
            } else {
                $stored = $value === null ? '' : (string) $value;
                if ($oldVal !== $stored) {
                    $changed[$key] = ['old' => $oldVal, 'new' => $stored];
                }
                $setting->update(['value' => $value]);
            }
        }

        if ($changed !== [] && auth()->check()) {
            [$oldText, $newText] = AdminSettingsAuditFormatter::generalSettingsSideBySide($changed);
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'settings_updated',
                'old_value' => $oldText,
                'new_value' => $newText,
                'created_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }

    /**
     * Sync ticket categories (tree: one level of subcategories under roots).
     */
    public function updateCategories(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'categories.*.client_key' => ['nullable', 'string', 'max:64'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.icon' => ['required', 'string', 'max:100'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'categories.*.parent_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'categories.*.parent_client_key' => ['nullable', 'string', 'max:64'],
        ]);

        $rows = $validated['categories'];
        $trimmedNames = collect($rows)->map(fn (array $r) => trim((string) $r['name']))->all();
        if (count($trimmedNames) !== count(array_unique($trimmedNames))) {
            throw ValidationException::withMessages([
                'categories' => 'Each category name must be unique.',
            ]);
        }

        foreach ($rows as $row) {
            $hasPid = isset($row['parent_id']) && $row['parent_id'] !== null && $row['parent_id'] !== '';
            $hasPck = ! empty($row['parent_client_key']);
            if ($hasPid && $hasPck) {
                throw ValidationException::withMessages([
                    'categories' => 'A subcategory cannot set both parent_id and parent_client_key.',
                ]);
            }
        }

        $existingIds = TicketCategory::query()->pluck('id')->all();
        $submittedIds = collect($rows)
            ->pluck('id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $removedIds = array_values(array_diff($existingIds, $submittedIds));

        if ($removedIds !== []) {
            $inUseIds = Ticket::query()
                ->whereIn('ticket_category_id', $removedIds)
                ->distinct()
                ->pluck('ticket_category_id')
                ->filter()
                ->values()
                ->all();
            if ($inUseIds !== []) {
                $labels = TicketCategory::query()->whereIn('id', $inUseIds)->pluck('name')->sort()->values()->all();
                $quoted = array_map(fn (string $n) => '"'.$n.'"', $labels);
                $list = implode(', ', $quoted);
                $message = count($labels) === 1
                    ? "Cannot remove category {$list} because one or more incidents still use it. Reassign those incidents first."
                    : "Cannot remove categories {$list} because incidents still use them. Reassign those incidents first.";

                throw ValidationException::withMessages([
                    'categories' => $message,
                ]);
            }
        }

        $beforeSnapshot = TicketCategory::orderedTreeForSettings()
            ->map(fn (TicketCategory $c): array => [
                'name' => ($c->parent_id ? '↳ ' : '').$c->name,
                'icon' => $c->icon,
            ])
            ->values()
            ->all();

        $roots = collect($rows)->filter(function (array $r): bool {
            $hasPid = isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '';
            $hasPck = ! empty($r['parent_client_key']);

            return ! $hasPid && ! $hasPck;
        })->sortBy('sort_order')->values();

        $children = collect($rows)->filter(function (array $r): bool {
            $hasPid = isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '';
            $hasPck = ! empty($r['parent_client_key']);

            return $hasPid || $hasPck;
        })->sortBy('sort_order')->values();

        if ($roots->isEmpty()) {
            throw ValidationException::withMessages([
                'categories' => 'At least one top-level category is required.',
            ]);
        }

        DB::transaction(function () use ($removedIds, $roots, $children): void {
            if ($removedIds !== []) {
                $toDelete = TicketCategory::query()->whereIn('id', $removedIds)->get();
                $childDeletes = $toDelete->whereNotNull('parent_id')->pluck('id')->all();
                $rootDeletes = $toDelete->whereNull('parent_id')->pluck('id')->all();
                if ($childDeletes !== []) {
                    TicketCategory::query()->whereIn('id', $childDeletes)->delete();
                }
                if ($rootDeletes !== []) {
                    TicketCategory::query()->whereIn('id', $rootDeletes)->delete();
                }
            }

            /** @var array<string, int> $clientKeyToId */
            $clientKeyToId = [];

            foreach ($roots as $r) {
                $data = [
                    'name' => trim((string) $r['name']),
                    'icon' => $r['icon'],
                    'parent_id' => null,
                    'sort_order' => (int) $r['sort_order'],
                ];
                if (! empty($r['id'])) {
                    TicketCategory::query()->whereKey((int) $r['id'])->update($data);
                    $id = (int) $r['id'];
                } else {
                    $c = TicketCategory::query()->create($data);
                    $id = $c->id;
                }
                if (! empty($r['client_key'])) {
                    $clientKeyToId[(string) $r['client_key']] = $id;
                }
            }

            foreach ($children as $r) {
                $hasPid = isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '';
                $parentId = $hasPid
                    ? (int) $r['parent_id']
                    : (int) ($clientKeyToId[(string) ($r['parent_client_key'] ?? '')] ?? 0);
                if ($parentId < 1) {
                    throw ValidationException::withMessages([
                        'categories' => 'Each subcategory must have a valid top-level parent.',
                    ]);
                }
                $parent = TicketCategory::query()->find($parentId);
                if ($parent === null || $parent->parent_id !== null) {
                    throw ValidationException::withMessages([
                        'categories' => 'Subcategories must be attached to a top-level category only.',
                    ]);
                }
                $data = [
                    'name' => trim((string) $r['name']),
                    'icon' => $r['icon'],
                    'parent_id' => $parentId,
                    'sort_order' => (int) $r['sort_order'],
                ];
                if (! empty($r['id'])) {
                    TicketCategory::query()->whereKey((int) $r['id'])->update($data);
                } else {
                    TicketCategory::query()->create($data);
                }
            }
        });

        $afterSnapshot = TicketCategory::orderedTreeForSettings()
            ->map(fn (TicketCategory $c): array => [
                'name' => ($c->parent_id ? '↳ ' : '').$c->name,
                'icon' => $c->icon,
            ])
            ->values()
            ->all();

        if (json_encode($beforeSnapshot) !== json_encode($afterSnapshot) && auth()->check()) {
            $oldText = AdminSettingsAuditFormatter::ticketCategoriesForAudit($beforeSnapshot);
            $newText = AdminSettingsAuditFormatter::ticketCategoriesForAudit($afterSnapshot);
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'ticket_categories_updated',
                'old_value' => $oldText,
                'new_value' => $newText,
                'created_at' => now(),
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
        $this->assertBuiltInPriorityIconsMatchDefaults($validated['priorities']);
        $this->assertBuiltInPriorityColorsMatchDefaults($validated['priorities']);

        $beforeSnapshot = TicketPriority::query()
            ->orderBy('sort_order')
            ->get(['name', 'icon', 'color'])
            ->map(fn (TicketPriority $p): array => [
                'name' => $p->name,
                'icon' => $p->icon,
                'color' => $p->color,
            ])
            ->values()
            ->all();

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
                    ? "Cannot remove priority {$list} because one or more incidents still use it. Reassign those incidents first."
                    : "Cannot remove priorities {$list} because incidents still use them. Reassign those incidents first.";

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

        $afterSnapshot = TicketPriority::query()
            ->orderBy('sort_order')
            ->get(['name', 'icon', 'color'])
            ->map(fn (TicketPriority $p): array => [
                'name' => $p->name,
                'icon' => $p->icon,
                'color' => $p->color,
            ])
            ->values()
            ->all();

        if (json_encode($beforeSnapshot) !== json_encode($afterSnapshot) && auth()->check()) {
            $oldText = AdminSettingsAuditFormatter::ticketPrioritiesForAudit($beforeSnapshot);
            $newText = AdminSettingsAuditFormatter::ticketPrioritiesForAudit($afterSnapshot);
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'ticket_priorities_updated',
                'old_value' => $oldText,
                'new_value' => $newText,
                'created_at' => now(),
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
        $this->assertBuiltInStatusIconsMatchDefaults($validated['statuses']);
        $this->assertBuiltInStatusColorsMatchDefaults($validated['statuses']);

        $beforeSnapshot = TicketStatus::query()
            ->orderBy('sort_order')
            ->get(['name', 'icon', 'color', 'handler_requirement'])
            ->map(fn (TicketStatus $s): array => [
                'name' => $s->name,
                'icon' => $s->icon,
                'color' => $s->color,
                'handler_requirement' => $s->handler_requirement,
            ])
            ->values()
            ->all();

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
                    ? "Cannot remove status {$list} because one or more incidents still use it. Reassign those incidents first."
                    : "Cannot remove statuses {$list} because incidents still use them. Reassign those incidents first.";

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

        $afterSnapshot = TicketStatus::query()
            ->orderBy('sort_order')
            ->get(['name', 'icon', 'color', 'handler_requirement'])
            ->map(fn (TicketStatus $s): array => [
                'name' => $s->name,
                'icon' => $s->icon,
                'color' => $s->color,
                'handler_requirement' => $s->handler_requirement,
            ])
            ->values()
            ->all();

        if (json_encode($beforeSnapshot) !== json_encode($afterSnapshot) && auth()->check()) {
            $oldText = AdminSettingsAuditFormatter::ticketStatusesForAudit($beforeSnapshot);
            $newText = AdminSettingsAuditFormatter::ticketStatusesForAudit($afterSnapshot);
            TicketActivity::create([
                'ticket_id' => null,
                'user_id' => auth()->id(),
                'action' => 'ticket_statuses_updated',
                'old_value' => $oldText,
                'new_value' => $newText,
                'created_at' => now(),
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
            'No incidents used that status.',
            fn (int $n): string => "{$n} incident(s) with status \"{$ticketStatus->name}\" were deleted. You can remove the status from the list, then save.",
        );
    }

    /**
     * Permanently delete all tickets in the given category (by DB category name).
     */
    public function destroyTicketsForCategory(TicketCategory $ticketCategory): RedirectResponse
    {
        return $this->destroyTicketsMatching(
            Ticket::query()->where(function (Builder $q) use ($ticketCategory): void {
                $q->where('ticket_category_id', $ticketCategory->id)
                    ->orWhere(function (Builder $q2) use ($ticketCategory): void {
                        $q2->whereNull('ticket_category_id')->where('category', $ticketCategory->name);
                    });
            }),
            'No incidents used that category.',
            fn (int $n): string => "{$n} incident(s) in category \"{$ticketCategory->name}\" were deleted. You can remove the category from the list, then save.",
        );
    }

    /**
     * Permanently delete all tickets with the given priority (by DB priority name).
     */
    public function destroyTicketsForPriority(TicketPriority $ticketPriority): RedirectResponse
    {
        return $this->destroyTicketsMatching(
            Ticket::query()->where('priority', $ticketPriority->name),
            'No incidents used that priority.',
            fn (int $n): string => "{$n} incident(s) with priority \"{$ticketPriority->name}\" were deleted. You can remove the priority from the list, then save.",
        );
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
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInPriorityIconsMatchDefaults(array $rows): void
    {
        $defaults = TicketConfigDefaults::priorityIconByName();

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! isset($defaults[$name])) {
                continue;
            }

            $expectedIcon = $defaults[$name];
            $actualIcon = (string) ($row['icon'] ?? '');

            if ($actualIcon !== $expectedIcon) {
                throw ValidationException::withMessages([
                    "priorities.{$index}.icon" => "The label and icon for \"{$name}\" are fixed and cannot be changed.",
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInStatusIconsMatchDefaults(array $rows): void
    {
        $defaults = TicketConfigDefaults::statusIconByName();

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! isset($defaults[$name])) {
                continue;
            }

            $expectedIcon = $defaults[$name];
            $actualIcon = (string) ($row['icon'] ?? '');

            if ($actualIcon !== $expectedIcon) {
                throw ValidationException::withMessages([
                    "statuses.{$index}.icon" => "The label and icon for \"{$name}\" are fixed and cannot be changed.",
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInPriorityColorsMatchDefaults(array $rows): void
    {
        $defaults = TicketConfigDefaults::priorityColorByName();

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! isset($defaults[$name])) {
                continue;
            }

            $expected = strtolower(trim($defaults[$name]));
            $actual = strtolower(trim((string) ($row['color'] ?? '')));

            if ($actual !== $expected) {
                throw ValidationException::withMessages([
                    "priorities.{$index}.color" => "The colour for \"{$name}\" is fixed and cannot be changed.",
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertBuiltInStatusColorsMatchDefaults(array $rows): void
    {
        $defaults = TicketConfigDefaults::statusColorByName();

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! isset($defaults[$name])) {
                continue;
            }

            $expected = strtolower(trim($defaults[$name]));
            $actual = strtolower(trim((string) ($row['color'] ?? '')));

            if ($actual !== $expected) {
                throw ValidationException::withMessages([
                    "statuses.{$index}.color" => "The colour for \"{$name}\" is fixed and cannot be changed.",
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
