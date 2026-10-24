<?php

use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Let administrators choose required uploads through the Form Builder. */
    public function up(): void
    {
        $formIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        DB::table('form_descriptions')
            ->whereIn('form_id', $formIds)
            ->whereIn('field_key', [
                'constitution_and_bylaws',
                'founding_members_list',
                'proposed_officers_list',
                'faculty_adviser_acceptance',
                'organization_logo',
                'gpoa_budget',
            ])
            ->update([
                'is_required' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Requirement choices are administrator-owned and cannot be inferred.
    }
};
