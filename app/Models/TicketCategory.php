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
}
