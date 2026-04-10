<?php

namespace App\Support;

/**
 * Built-in ticket categories, priorities, and statuses shipped with the app.
 * Seeded defaults for categories, priorities, and statuses. Categories are fully editable in admin
 * settings (at least one category must remain). Priorities and statuses keep built-in name/icon rules.
 */
final class TicketConfigDefaults
{
    /**
     * @return list<array{name: string, icon: string}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Network', 'icon' => 'Network'],
            ['name' => 'Hardware', 'icon' => 'HardDrive'],
            ['name' => 'Software', 'icon' => 'Code'],
            ['name' => 'Access', 'icon' => 'Key'],
            ['name' => 'Security', 'icon' => 'Shield'],
            ['name' => 'Others', 'icon' => 'HelpCircle'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function categoryNames(): array
    {
        return array_column(self::categories(), 'name');
    }

    /**
     * Default category names except optional "Others" (for seeds and migrations; not enforced as locked in settings).
     *
     * @return list<string>
     */
    public static function protectedCategoryNames(): array
    {
        return array_values(array_filter(
            self::categoryNames(),
            fn (string $name) => $name !== 'Others',
        ));
    }

    /**
     * Built-in category name => icon (excludes optional "Others").
     *
     * @return array<string, string>
     */
    public static function protectedCategoryIconByName(): array
    {
        $map = [];
        foreach (self::categories() as $row) {
            if ($row['name'] === 'Others') {
                continue;
            }
            $map[$row['name']] = $row['icon'];
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function priorityIconByName(): array
    {
        return collect(self::priorities())->mapWithKeys(
            fn (array $row): array => [$row['name'] => $row['icon']],
        )->all();
    }

    /**
     * @return array<string, string>
     */
    public static function statusIconByName(): array
    {
        return collect(self::statuses())->mapWithKeys(
            fn (array $row): array => [$row['name'] => $row['icon']],
        )->all();
    }

    /**
     * @return array<string, string>
     */
    public static function priorityColorByName(): array
    {
        return collect(self::priorities())->mapWithKeys(
            fn (array $row): array => [$row['name'] => $row['color']],
        )->all();
    }

    /**
     * @return array<string, string>
     */
    public static function statusColorByName(): array
    {
        return collect(self::statuses())->mapWithKeys(
            fn (array $row): array => [$row['name'] => $row['color']],
        )->all();
    }

    /**
     * @return list<array{name: string, icon: string, color: string}>
     */
    public static function priorities(): array
    {
        return [
            ['name' => 'Critical', 'icon' => 'AlertCircle', 'color' => '#f43f5e'],
            ['name' => 'High', 'icon' => 'AlertTriangle', 'color' => '#f97316'],
            ['name' => 'Medium', 'icon' => 'ArrowUpCircle', 'color' => '#eab308'],
            ['name' => 'Low', 'icon' => 'Circle', 'color' => '#60a5fa'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function priorityNames(): array
    {
        return array_column(self::priorities(), 'name');
    }

    /**
     * @return list<array{name: string, icon: string, color: string, handler_requirement: string}>
     */
    public static function statuses(): array
    {
        return [
            ['name' => 'Open', 'icon' => 'AlertTriangle', 'color' => '#f43f5e', 'handler_requirement' => 'none'],
            ['name' => 'In Progress', 'icon' => 'Play', 'color' => '#3b82f6', 'handler_requirement' => 'required'],
            ['name' => 'On Hold', 'icon' => 'Pause', 'color' => '#f59e0b', 'handler_requirement' => 'required'],
            ['name' => 'Resolved', 'icon' => 'CheckCircle2', 'color' => '#059669', 'handler_requirement' => 'required'],
            ['name' => 'Cancelled', 'icon' => 'Ban', 'color' => '#64748b', 'handler_requirement' => 'optional'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusNames(): array
    {
        return array_column(self::statuses(), 'name');
    }
}
