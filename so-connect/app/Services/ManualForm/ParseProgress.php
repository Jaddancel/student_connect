<?php

namespace App\Services\ManualForm;

use App\Models\ManualFormSession;
use App\Models\ManualFormSessionDocument;
use Illuminate\Support\Facades\Cache;

/**
 * Live progress of a scan parse, kept in the cache (not the database) so the
 * "Reading…" screen can show a progress bar while the job runs. Best-effort:
 * a missing entry simply means "starting".
 */
class ParseProgress
{
    private const TTL_SECONDS = 1800;

    public static function key(ManualFormSession|ManualFormSessionDocument $target): string
    {
        return $target instanceof ManualFormSessionDocument
            ? 'manual-parse-progress:doc:'.$target->getKey()
            : 'manual-parse-progress:session:'.$target->getKey();
    }

    public static function set(ManualFormSession|ManualFormSessionDocument $target, int $percent, string $label): void
    {
        try {
            Cache::put(self::key($target), [
                'percent' => max(0, min(100, $percent)),
                'label' => $label,
            ], self::TTL_SECONDS);
        } catch (\Throwable) {
            // Progress is cosmetic; never fail a parse over it.
        }
    }

    /**
     * @return array{percent:int,label:string}|null
     */
    public static function get(ManualFormSession|ManualFormSessionDocument $target): ?array
    {
        if ($target->status !== ManualFormSession::STATUS_PARSING) {
            return null;
        }

        try {
            $value = Cache::get(self::key($target));
        } catch (\Throwable) {
            return null;
        }

        return is_array($value) ? $value : ['percent' => 0, 'label' => 'Waiting for the reader to start…'];
    }

    public static function clear(ManualFormSession|ManualFormSessionDocument $target): void
    {
        try {
            Cache::forget(self::key($target));
        } catch (\Throwable) {
        }
    }
}
