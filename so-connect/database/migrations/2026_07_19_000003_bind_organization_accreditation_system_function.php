<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Live-install data migration: bind the existing "Organization Recognition"
 * form (route organization-recognition) to the new org_accreditation system
 * function and rename it. Idempotent, and skipped if anything already claims the
 * function (system_function is unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        $alreadyBound = DB::table('forms')->where('system_function', 'org_accreditation')->exists();

        if (! $alreadyBound) {
            DB::table('forms')
                ->where('route_name', 'organization-recognition')
                ->whereNull('system_function')
                ->update([
                    'system_function' => 'org_accreditation',
                    'name' => 'Organization Accreditation',
                ]);
        }
    }

    public function down(): void
    {
        DB::table('forms')
            ->where('route_name', 'organization-recognition')
            ->where('system_function', 'org_accreditation')
            ->update([
                'system_function' => null,
                'name' => 'Organization Recognition',
            ]);
    }
};
