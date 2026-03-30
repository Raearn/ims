<?php

namespace Tests\Unit;

use App\Enums\TicketStatusHandlerRequirement;
use App\Support\AdminSettingsAuditFormatter;
use PHPUnit\Framework\TestCase;

class AdminSettingsAuditFormatterTest extends TestCase
{
    public function test_general_settings_side_by_side_orders_keys_and_formats_lines(): void
    {
        [$old, $new] = AdminSettingsAuditFormatter::generalSettingsSideBySide([
            'beta' => ['old' => '1', 'new' => '2'],
            'alpha' => ['old' => 'x', 'new' => 'y'],
        ]);

        $this->assertSame("alpha: x\nbeta: 1", $old);
        $this->assertSame("alpha: y\nbeta: 2", $new);
    }

    public function test_ticket_config_rows_side_by_side_includes_fields(): void
    {
        $text = AdminSettingsAuditFormatter::ticketConfigRowsSideBySide([
            ['name' => 'Open', 'icon' => 'Circle', 'color' => '#fff'],
        ], ['icon', 'color']);

        $this->assertSame("Open\n  Icon: Circle\n  Color: #fff", $text);
    }

    public function test_ticket_config_rows_side_by_side_stringifies_backed_enum_values(): void
    {
        $text = AdminSettingsAuditFormatter::ticketConfigRowsSideBySide([
            [
                'name' => 'In Progress',
                'icon' => 'Loader',
                'color' => '#0ea5e9',
                'handler_requirement' => TicketStatusHandlerRequirement::Required,
            ],
        ], ['icon', 'color', 'handler_requirement']);

        $this->assertSame("In Progress\n  Icon: Loader\n  Color: #0ea5e9\n  Handler: required", $text);
    }
}
