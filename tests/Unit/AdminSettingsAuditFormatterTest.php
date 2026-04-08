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

    public function test_ticket_statuses_for_audit_outputs_compact_bullet_lines(): void
    {
        $text = AdminSettingsAuditFormatter::ticketStatusesForAudit([
            [
                'name' => 'Open',
                'icon' => 'AlertTriangle',
                'color' => '#f43f5e',
                'handler_requirement' => TicketStatusHandlerRequirement::None,
            ],
            [
                'name' => 'In Progress',
                'icon' => 'Play',
                'color' => '#3b82f6',
                'handler_requirement' => TicketStatusHandlerRequirement::Required,
            ],
            [
                'name' => 'Cancelled',
                'icon' => 'Ban',
                'color' => '#64748b',
                'handler_requirement' => TicketStatusHandlerRequirement::Optional,
            ],
        ]);

        $expected = <<<'TXT'
• Open · AlertTriangle · #f43f5e · —
• In Progress · Play · #3b82f6 · Required
• Cancelled · Ban · #64748b · Optional
TXT;
        $this->assertSame($expected, $text);
    }

    public function test_ticket_categories_for_audit_outputs_compact_bullet_lines(): void
    {
        $text = AdminSettingsAuditFormatter::ticketCategoriesForAudit([
            ['name' => 'Network', 'icon' => 'Wifi'],
            ['name' => 'Uncategorized', 'icon' => null],
        ]);

        $expected = <<<'TXT'
• Network · Wifi
• Uncategorized · —
TXT;
        $this->assertSame($expected, $text);
    }

    public function test_ticket_priorities_for_audit_outputs_compact_bullet_lines(): void
    {
        $text = AdminSettingsAuditFormatter::ticketPrioritiesForAudit([
            ['name' => 'High', 'icon' => 'AlertTriangle', 'color' => '#f97316'],
            ['name' => 'Low', 'icon' => '', 'color' => ''],
        ]);

        $expected = <<<'TXT'
• High · AlertTriangle · #f97316
• Low · — · —
TXT;
        $this->assertSame($expected, $text);
    }
}
