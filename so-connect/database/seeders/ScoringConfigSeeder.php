<?php

namespace Database\Seeders;

use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Services\Scoring\ScoringCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the configurable scoring catalog from the canonical system values in
 * {@see ScoringCatalog} (the exact caps/weights/labels the engine used when
 * they were hardcoded — score parity is the contract). Idempotent: existing
 * rows are updated by key, admin-created criteria are left alone.
 */
class ScoringConfigSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach (ScoringCatalog::SYSTEM_CATEGORIES as $key => $meta) {
            ScoringCategory::query()->updateOrCreate(
                ['key' => $key],
                ['label' => $meta['label'], 'cap' => $meta['cap'], 'sort_order' => $sort++],
            );
        }

        $sort = 0;
        foreach (ScoringCatalog::SYSTEM_CRITERIA as $key => [$category, $label, $weight]) {
            ScoringCriterion::query()->updateOrCreate(
                ['key' => $key],
                [
                    'category_key' => $category,
                    'label' => $label,
                    'weight' => $weight,
                    'sort_order' => $sort++,
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }

        ScoringCatalog::flush();
    }
}
