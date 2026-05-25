<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SaasSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('saas_settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
