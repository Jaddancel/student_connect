<?php

namespace App\Services;

use App\Models\ActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Single writer for the administrator action log. Instrumented call sites
 * (auth events, scoring saves, form-builder and ID-template CRUD, scoring
 * rule-editor changes) call the static log() and never worry about failures —
 * logging must never break the request that triggered it.
 */
class ActionLogger
{
    public const CATEGORY_AUTH = 'auth';

    public const CATEGORY_SCORING = 'scoring';

    public const CATEGORY_SCORING_CONFIG = 'scoring_config';

    public const CATEGORY_FORM_BUILDER = 'form_builder';

    public const CATEGORY_ID_TEMPLATE = 'id_template';

    public const CATEGORY_SETTINGS = 'settings';

    /**
     * Human labels for the category filter dropdown/exports.
     *
     * @return array<string,string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_AUTH => 'Log In / Log Out',
            self::CATEGORY_SCORING => 'Organization Scoring',
            self::CATEGORY_SCORING_CONFIG => 'Scoring Rules & Criteria',
            self::CATEGORY_FORM_BUILDER => 'Form Editing / Creation',
            self::CATEGORY_ID_TEMPLATE => 'ID Template Editing / Creation',
            self::CATEGORY_SETTINGS => 'Settings',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return self::categories()[$category] ?? $category;
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    public static function log(
        string $category,
        string $action,
        ?string $description = null,
        array $meta = [],
        ?Model $subject = null,
        ?int $userId = null,
    ): void {
        try {
            ActionLog::query()->create([
                'user_id' => $userId ?? auth()->id(),
                'category' => $category,
                'action' => $action,
                'description' => $description !== null ? mb_substr($description, 0, 500) : null,
                'meta' => $meta !== [] ? $meta : null,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Action log write failed: '.$e->getMessage());
        }
    }
}
