<?php

namespace App\Services\Scoring;

use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use Illuminate\Support\Facades\Schema;

/**
 * Single source for the scoring configuration: categories (with caps) and
 * criteria (with weights/labels). Reads the scoring_categories /
 * scoring_criteria tables; when they are absent or empty (pre-migration
 * installs, tests) it falls back to SYSTEM_* — the exact values previously
 * hardcoded in OrganizationScoringController, so scores never change simply
 * because the catalog moved to the database.
 */
class ScoringCatalog
{
    /** Category key => [label, cap]. Caps mirror the legacy min() clamps. */
    public const SYSTEM_CATEGORIES = [
        'cat1' => ['label' => 'I. Programs & Activities', 'cap' => 100],
        'cat2' => ['label' => 'II. Participation & Representation', 'cap' => 100],
        'cat3' => ['label' => 'III. Awards & Recognition', 'cap' => 50],
        'cat4' => ['label' => 'IV. Extension Services', 'cap' => 100],
        'cat5' => ['label' => 'V. Tangible Projects', 'cap' => 100],
        'cat6' => ['label' => 'VI. Documents & Meetings', 'cap' => 100],
    ];

    /** Criterion key => [category, label, weight]. Weights mirror computeScores(). */
    public const SYSTEM_CRITERIA = [
        'cat1_seminar_college'        => ['cat1', 'Seminars - College Level (>=15 members)', 10],
        'cat1_seminar_univ'           => ['cat1', 'Seminars - University Level (>=30 members)', 15],
        'cat1_activities_related'     => ['cat1', 'Activities Related to Org (>=15 members)', 10],
        'cat1_activities_not_related' => ['cat1', 'Activities Not Related to Org (>=15 members)', 7],
        'cat1_donation_cash'          => ['cat1', 'Donation - Cash (>=PHP 200, per approved project)', 2],
        'cat1_donation_kinds'         => ['cat1', 'Donation - In Kind (per in-kind row)', 10],
        'cat1_cosponsor_pts'          => ['cat1', 'Co-sponsorship Points (pre-computed)', 1],
        'cat1_income'                 => ['cat1', 'Income Generated (per PHP 500)', 1],
        'cat2_other_orgs'             => ['cat2', 'Activities co-sponsored by other orgs', 10],
        'cat2_rep_local'              => ['cat2', 'Representative - Local scope', 2],
        'cat2_rep_provincial'         => ['cat2', 'Representative - Provincial scope', 4],
        'cat2_rep_regional'           => ['cat2', 'Representative - Regional scope', 6],
        'cat2_rep_national'           => ['cat2', 'Representative - National scope', 8],
        'cat2_rep_international'      => ['cat2', 'Representative - International scope', 10],
        'cat2_ssc_osa_activities'     => ['cat2', 'SSC/OSA-sponsored activities', 10],
        'cat2_ssc_seminars'           => ['cat2', 'SSC-sponsored seminars', 10],
        'cat2_other_seminars'         => ['cat2', 'Other org seminars/conferences', 7],
        'cat2_osa_seminars'           => ['cat2', 'OSA/Admin seminars', 5],
        'cat2_ssc_meeting_rep'        => ['cat2', 'SSC meeting - Representative', 2],
        'cat2_ssc_meeting_proxy'      => ['cat2', 'SSC meeting - Proxy', 1],
        'cat2_help_ssc_osa'           => ['cat2', 'Preparation/help for SSC/OSA', 5],
        'cat2_help_others'            => ['cat2', 'Preparation/help for other orgs', 3],
        'cat3_group_intl'             => ['cat3', 'Group Award - International', 20],
        'cat3_group_national'         => ['cat3', 'Group Award - National', 10],
        'cat3_group_regional'         => ['cat3', 'Group Award - Regional', 7],
        'cat3_group_provincial'       => ['cat3', 'Group Award - Provincial', 5],
        'cat3_group_local'            => ['cat3', 'Group Award - Local', 3],
        'cat3_individual_intl'        => ['cat3', 'Individual Award - International', 10],
        'cat3_individual_national'    => ['cat3', 'Individual Award - National', 7],
        'cat3_individual_regional'    => ['cat3', 'Individual Award - Regional', 5],
        'cat3_individual_provincial'  => ['cat3', 'Individual Award - Provincial', 2],
        'cat3_individual_local'       => ['cat3', 'Individual Award - Local', 1],
        'cat4_extension_groups'       => ['cat4', 'Extension Service Groups (>=10 members)', 10],
        'cat5_tangible_projects'      => ['cat5', 'Approved Tangible Projects', 100],
        'cat6_documents'              => ['cat6', 'Required Documents Submitted', 50],
        'cat6_meetings'               => ['cat6', 'General Meetings with Minutes (>30 min)', 25],
        'cat6_leadership'             => ['cat6', 'Leadership Training Participated', 15],
        'cat6_transparency'           => ['cat6', 'Financial/Transparency Report Submitted', 10],
    ];

    /** @var array<string,array{label:string,cap:int}>|null request-scoped cache */
    private static ?array $categories = null;

    /** @var array<string,array{category:string,label:string,weight:int,is_system:bool}>|null */
    private static ?array $criteria = null;

    /** Drop the request-scoped cache (call after catalog mutations). */
    public static function flush(): void
    {
        self::$categories = null;
        self::$criteria = null;
    }

    /**
     * @return array<string,array{label:string,cap:int}> key => meta, sorted
     */
    public static function categories(): array
    {
        if (self::$categories !== null) {
            return self::$categories;
        }

        $fromDb = [];
        try {
            if (Schema::hasTable('scoring_categories')) {
                foreach (ScoringCategory::query()->orderBy('sort_order')->get() as $row) {
                    $fromDb[$row->key] = ['label' => $row->label, 'cap' => (int) $row->cap];
                }
            }
        } catch (\Throwable) {
            $fromDb = [];
        }

        return self::$categories = $fromDb !== [] ? $fromDb : self::SYSTEM_CATEGORIES;
    }

    /**
     * @return array<string,array{category:string,label:string,weight:int,is_system:bool}> key => meta
     */
    public static function criteria(): array
    {
        if (self::$criteria !== null) {
            return self::$criteria;
        }

        $fromDb = [];
        try {
            if (Schema::hasTable('scoring_criteria')) {
                $rows = ScoringCriterion::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('scoring_criterion_id')
                    ->get();
                foreach ($rows as $row) {
                    $fromDb[$row->key] = [
                        'category' => $row->category_key,
                        'label' => $row->label,
                        'weight' => (int) $row->weight,
                        'is_system' => (bool) $row->is_system,
                    ];
                }
            }
        } catch (\Throwable) {
            $fromDb = [];
        }

        if ($fromDb !== []) {
            return self::$criteria = $fromDb;
        }

        $fallback = [];
        foreach (self::SYSTEM_CRITERIA as $key => [$category, $label, $weight]) {
            $fallback[$key] = [
                'category' => $category,
                'label' => $label,
                'weight' => $weight,
                'is_system' => true,
            ];
        }

        return self::$criteria = $fallback;
    }

    /**
     * @return string[] every active criterion key
     */
    public static function keys(): array
    {
        return array_keys(self::criteria());
    }

    /**
     * @return array<string,string> criterion key => label (audit/manual views)
     */
    public static function labels(): array
    {
        return array_map(static fn (array $meta) => $meta['label'], self::criteria());
    }

    /**
     * Custom (admin-created) criteria only, grouped by category key — the
     * scoring form renders these dynamically below the fixed system grid.
     *
     * @return array<string,array<string,array{category:string,label:string,weight:int,is_system:bool}>>
     */
    public static function customByCategory(): array
    {
        $grouped = [];
        foreach (self::criteria() as $key => $meta) {
            if (! $meta['is_system']) {
                $grouped[$meta['category']][$key] = $meta;
            }
        }

        return $grouped;
    }
}
