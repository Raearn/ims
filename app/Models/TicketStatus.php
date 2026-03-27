<?php

namespace App\Models;

use App\Enums\TicketStatusHandlerRequirement;
use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'color',
        'handler_requirement',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handler_requirement' => TicketStatusHandlerRequirement::class,
        ];
    }

    /**
     * Status names where creating/updating a ticket requires at least one handler.
     *
     * @return list<string>
     */
    public static function namesRequiringHandlersForForms(): array
    {
        return static::query()
            ->where('handler_requirement', TicketStatusHandlerRequirement::Required)
            ->pluck('name')
            ->all();
    }

    /**
     * Status names where the ticket UI omits handlers (queue / inbox states).
     *
     * @return list<string>
     */
    public static function namesWithNoHandlersInUi(): array
    {
        return static::query()
            ->where('handler_requirement', TicketStatusHandlerRequirement::None)
            ->pluck('name')
            ->all();
    }

    /**
     * Status names allowed as targets when leaving a no-handler status (assign flow).
     *
     * @return list<string>
     */
    public static function namesAllowedWhenLeavingNoHandlerStatus(): array
    {
        return static::query()
            ->whereNot('handler_requirement', TicketStatusHandlerRequirement::None)
            ->pluck('name')
            ->all();
    }
}
