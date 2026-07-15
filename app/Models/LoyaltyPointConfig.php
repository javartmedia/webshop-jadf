<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LoyaltyPointConfig extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember('loyalty_config_' . $key, 3600, function () use ($key, $default) {
            $config = static::where('key', $key)->first();
            return $config ? $config->value : $default;
        });
    }
}
