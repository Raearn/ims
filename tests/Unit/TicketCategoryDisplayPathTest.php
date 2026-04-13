<?php

namespace Tests\Unit;

use App\Models\TicketCategory;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class TicketCategoryDisplayPathTest extends TestCase
{
    public function test_display_path_lookup_builds_parent_child_label(): void
    {
        $network = new TicketCategory;
        $network->forceFill(['id' => 1, 'name' => 'Network', 'parent_id' => null]);

        $isp = new TicketCategory;
        $isp->forceFill(['id' => 2, 'name' => 'ISP Globe', 'parent_id' => 1]);

        $lookup = TicketCategory::displayPathLookup(new Collection([$network, $isp]));

        self::assertSame('Network', $lookup[1]);
        self::assertSame('Network - ISP Globe', $lookup[2]);
    }

    public function test_display_label_for_ticket_uses_lookup_when_category_id_set(): void
    {
        $paths = [2 => 'Network - ISP Globe'];

        self::assertSame('Network - ISP Globe', TicketCategory::displayLabelForTicket(2, 'ISP Globe', $paths));
    }

    public function test_display_label_falls_back_when_id_missing_from_lookup(): void
    {
        self::assertSame('ISP Globe', TicketCategory::displayLabelForTicket(99, 'ISP Globe', []));
    }

    public function test_display_label_falls_back_when_ticket_category_id_null(): void
    {
        self::assertSame('Legacy Leaf', TicketCategory::displayLabelForTicket(null, 'Legacy Leaf', [1 => 'Root']));
    }

    public function test_deep_hierarchy_joins_all_levels(): void
    {
        $a = new TicketCategory;
        $a->forceFill(['id' => 1, 'name' => 'A', 'parent_id' => null]);
        $b = new TicketCategory;
        $b->forceFill(['id' => 2, 'name' => 'B', 'parent_id' => 1]);
        $c = new TicketCategory;
        $c->forceFill(['id' => 3, 'name' => 'C', 'parent_id' => 2]);

        $lookup = TicketCategory::displayPathLookup(new Collection([$a, $b, $c]));

        self::assertSame('A - B - C', $lookup[3]);
    }
}
