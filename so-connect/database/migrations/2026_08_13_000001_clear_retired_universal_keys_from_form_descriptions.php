<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears universal keys that were retired from the {@see App\Support\UniversalField}
 * catalog when it was pruned to the curated profile subset, but were left stamped on
 * already-seeded forms (the Directory of Student Officers still carried
 * `contact_number`). The form builder validates `universal_key` with
 * `Rule::in(UniversalField::keys())`, so a form holding a retired key could not be
 * saved at all — it failed with "The selected fields.N.universal_key is invalid."
 *
 * The list is hardcoded rather than derived from the live catalog so this migration
 * stays deterministic if the catalog changes again later.
 */
return new class extends Migration
{
    private const RETIRED_KEYS = [
        'contact_number',
        'age',
        'nationality',
        'course_year',
        'student_id',
        'id_photo_front',
        'id_photo_back',
        'photo',
        'address',
    ];

    public function up(): void
    {
        DB::table('form_descriptions')
            ->whereIn('universal_key', self::RETIRED_KEYS)
            ->update(['universal_key' => null]);
    }

    /**
     * Irreversible: the retired keys are gone from the catalog, so restoring them
     * would only recreate the unsaveable state this migration repairs.
     */
    public function down(): void {}
};
