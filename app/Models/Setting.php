<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember('setting_' . $key, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            static::create(['key' => $key, 'value' => $value]);
        }
        Cache::forget('setting_' . $key);
    }

    public static function getAllPublicSettings(): array
    {
        return Cache::remember('public_settings', 3600, function () {
            return static::where('is_public', true)->pluck('value', 'key')->toArray();
        });
    }

    public static function getGroupSettings(string $group): array
    {
        return Cache::remember('settings_group_' . $group, 3600, function () use ($group) {
            return static::where('group', $group)->pluck('value', 'key')->toArray();
        });
    }

    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            Cache::forget('setting_' . $setting->key);
            Cache::forget('public_settings');
            Cache::forget('settings_group_' . $setting->group);
        });

        static::deleted(function (Setting $setting) {
            Cache::forget('setting_' . $setting->key);
            Cache::forget('public_settings');
            Cache::forget('settings_group_' . $setting->group);
        });
    }
}
