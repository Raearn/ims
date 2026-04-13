<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TicketCategory extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'sort_order',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_category_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Roots in sort order, each followed by its children (admin settings / incident pickers).
     *
     * @return Collection<int, TicketCategory>
     */
    public static function orderedTreeForSettings(): Collection
    {
        $roots = static::query()->whereNull('parent_id')->orderBy('sort_order')->get();
        $byParent = static::query()->whereNotNull('parent_id')->orderBy('sort_order')->get()->groupBy('parent_id');
        $out = collect();
        foreach ($roots as $root) {
            $out->push($root);
            foreach ($byParent->get($root->id, collect()) as $child) {
                $out->push($child);
            }
        }
        $rootIds = $roots->pluck('id')->all();
        foreach ($byParent as $parentId => $group) {
            if (! in_array((int) $parentId, $rootIds, true)) {
                foreach ($group as $c) {
                    $out->push($c);
                }
            }
        }

        return $out;
    }

    /**
     * Map each category id to a breadcrumb label ("Root - Child - Leaf") for list/detail display.
     *
     * @param  Collection<int, TicketCategory>  $categories
     * @return array<int, string>
     */
    public static function displayPathLookup(Collection $categories): array
    {
        $byId = $categories->keyBy('id');
        $out = [];

        foreach ($byId as $id => $row) {
            $chain = [];
            $seen = [];
            $walk = (int) $id;

            while (true) {
                if (isset($seen[$walk])) {
                    break;
                }
                $seen[$walk] = true;

                $node = $byId->get($walk);
                if ($node === null) {
                    $chain = [];
                    break;
                }

                array_unshift($chain, $node->name);

                if ($node->parent_id === null) {
                    break;
                }

                $walk = (int) $node->parent_id;
            }

            if ($chain !== []) {
                $out[(int) $id] = implode(' - ', $chain);
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public static function displayPathLookupFromSettings(): array
    {
        return self::displayPathLookup(self::orderedTreeForSettings());
    }

    /**
     * @param  array<int, string>  $pathByCategoryId  From {@see displayPathLookup()}
     */
    public static function displayLabelForTicket(?int $ticketCategoryId, string $denormalizedFallback, array $pathByCategoryId): string
    {
        if ($ticketCategoryId !== null) {
            $path = $pathByCategoryId[$ticketCategoryId] ?? null;
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return $denormalizedFallback;
    }
}
