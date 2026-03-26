<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Cast the value based on the stored type column.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): mixed {
                return match ($attributes['type'] ?? 'string') {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'json' => json_decode($value, true),
                    'integer' => (int) $value,
                    default => $value,
                };
            },
            set: function (mixed $value, array $attributes): string {
                $type = $attributes['type'] ?? 'string';

                if ($type === 'json' && (is_array($value) || is_object($value))) {
                    return json_encode($value);
                }

                if ($type === 'boolean') {
                    return $value ? '1' : '0';
                }

                return (string) $value;
            },
        );
    }

    /**
     * Retrieve a single setting value by key, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }
}
