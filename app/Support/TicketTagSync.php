<?php

namespace App\Support;

use App\Models\Tag;
use App\Models\Ticket;

class TicketTagSync
{
    /**
     * @param  list<string>|array<int, mixed>  $raw
     * @return list<string>
     */
    public static function normalizedNames(array $raw): array
    {
        return collect($raw)
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|array<int, mixed>  $rawNames
     * @return list<string>
     */
    public static function sync(Ticket $ticket, array $rawNames): array
    {
        $names = self::normalizedNames($rawNames);
        $ids = collect($names)
            ->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id)
            ->all();
        $ticket->tags()->sync($ids);

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function currentSortedNames(Ticket $ticket): array
    {
        return $ticket->tags()->pluck('name')->sort()->values()->all();
    }
}
