<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value application settings. Reads are cached per key; writes forget the
 * cache. Values are JSON-cast so callers store/read arrays or scalars directly.
 *
 * Known keys:
 *  - accreditation.conditions   (array AST — Phase 5)
 *  - accreditation.notify_days  (int, default 7)
 *  - accreditation.purge_grace_days (int, default 30)
 *  - backup.interval_hours      (int, default 24)
 *  - after_event.elapsed_days   (int, default 3)
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    private static function cacheKey(string $key): string
    {
        return 'app_setting:'.$key;
    }

    /**
     * Read a setting, falling back to $default when unset.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::cacheKey($key), function () use ($key) {
            $row = static::query()->where('key', $key)->first();

            // Wrap so a genuine null value is distinguishable from "missing".
            return $row ? ['v' => $row->value] : null;
        });

        return $value === null ? $default : $value['v'];
    }

    /**
     * Write a setting and forget its cache entry.
     */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }
}
