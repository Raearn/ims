<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Str;
use UnitEnum;

class AdminSettingsAuditFormatter
{
    /**
     * Build parallel "before" and "after" text for general settings (one line per key).
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changed
     * @return array{0: string, 1: string}
     */
    public static function generalSettingsSideBySide(array $changed): array
    {
        ksort($changed);
        $oldLines = [];
        $newLines = [];
        foreach ($changed as $key => $pair) {
            $oldLines[] = $key.': '.self::stringifyValue($pair['old']);
            $newLines[] = $key.': '.self::stringifyValue($pair['new']);
        }

        return [implode("\n", $oldLines), implode("\n", $newLines)];
    }

    /**
     * Compact one line per ticket status for audit log (no verbose key names).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function ticketStatusesForAudit(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $icon = trim(self::stringifyValue($row['icon'] ?? null));
            $color = trim(self::stringifyValue($row['color'] ?? null));
            $handler = self::handlerRequirementDisplay($row['handler_requirement'] ?? null);
            $iconOut = $icon !== '' ? $icon : '—';
            $colorOut = $color !== '' ? $color : '—';
            $lines[] = '• '.$name.' · '.$iconOut.' · '.$colorOut.' · '.$handler;
        }

        return implode("\n", $lines);
    }

    /**
     * Compact one line per category: name · icon (for audit log UI).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function ticketCategoriesForAudit(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $icon = trim(self::stringifyValue($row['icon'] ?? null));
            $iconOut = $icon !== '' ? $icon : '—';
            $lines[] = '• '.$name.' · '.$iconOut;
        }

        return implode("\n", $lines);
    }

    /**
     * Compact one line per priority: name · icon · color (for audit log UI).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function ticketPrioritiesForAudit(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $icon = trim(self::stringifyValue($row['icon'] ?? null));
            $color = trim(self::stringifyValue($row['color'] ?? null));
            $iconOut = $icon !== '' ? $icon : '—';
            $colorOut = $color !== '' ? $color : '—';
            $lines[] = '• '.$name.' · '.$iconOut.' · '.$colorOut;
        }

        return implode("\n", $lines);
    }

    /**
     * Multi-line blocks per row (name + indented fields) for readable audit log diffs.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $fields  Attribute keys to show after the name
     */
    public static function ticketConfigRowsSideBySide(array $rows, array $fields): string
    {
        $blocks = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            $lines = [$name];
            foreach ($fields as $f) {
                $label = self::ticketConfigFieldLabel($f);
                $lines[] = '  '.$label.': '.self::stringifyValue($row[$f] ?? null);
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    private static function ticketConfigFieldLabel(string $key): string
    {
        return match ($key) {
            'handler_requirement' => 'Handler',
            'icon' => 'Icon',
            'color' => 'Color',
            default => Str::title(str_replace('_', ' ', $key)),
        };
    }

    private static function handlerRequirementDisplay(mixed $v): string
    {
        if ($v instanceof BackedEnum) {
            $v = (string) $v->value;
        } else {
            $v = trim((string) ($v ?? ''));
        }

        return match ($v) {
            'none' => '—',
            'optional' => 'Optional',
            'required' => 'Required',
            '' => '—',
            default => $v,
        };
    }

    private static function stringifyValue(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        if ($v instanceof BackedEnum) {
            return Str::limit((string) $v->value, 500);
        }

        if ($v instanceof UnitEnum) {
            return Str::limit($v->name, 500);
        }

        if (is_array($v)) {
            $json = json_encode($v, JSON_UNESCAPED_UNICODE) ?: '[unserializable]';

            return Str::limit($json, 500);
        }

        return Str::limit((string) $v, 500);
    }
}
