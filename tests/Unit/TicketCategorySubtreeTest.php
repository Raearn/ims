<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mirrors resources/js/lib/ticketCategoryFilter.ts collectDescendantCategoryIds (BFS).
 * Update both when changing subtree rules.
 */
final class TicketCategorySubtreeTest extends TestCase
{
    /**
     * @param  array<int, array{id: int, parent_id: int|null}>  $categories
     * @return list<int>
     */
    private static function subtreeIds(int $rootId, array $categories): array
    {
        $ids = [$rootId => true];
        $queue = [$rootId];
        while ($queue !== []) {
            $parentId = array_shift($queue);
            foreach ($categories as $c) {
                if ($c['parent_id'] === $parentId && ! isset($ids[$c['id']])) {
                    $ids[$c['id']] = true;
                    $queue[] = $c['id'];
                }
            }
        }

        $list = array_keys($ids);
        sort($list);

        return $list;
    }

    public function test_parent_includes_direct_children(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 1],
        ];
        self::assertSame([1, 2, 3], self::subtreeIds(1, $categories));
    }

    public function test_parent_includes_nested_descendants(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 2],
        ];
        self::assertSame([1, 2, 3], self::subtreeIds(1, $categories));
    }

    public function test_child_filter_is_only_subtree_from_that_node(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => null],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 1],
        ];
        self::assertSame([2], self::subtreeIds(2, $categories));
        self::assertSame([3], self::subtreeIds(3, $categories));
    }
}
