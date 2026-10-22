<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $memberships = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->join('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->join('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_type', 7)
            ->where('od.name', 'like', 'College of%Student Council')
            ->get(['u.profile as profile_id', 'od.name']);

        foreach ($memberships as $membership) {
            $college = trim((string) preg_replace('/\s*-\s*Student Council\s*$/i', '', (string) $membership->name));
            if ($college === '') {
                continue;
            }

            DB::table('profiles')
                ->where('profile_id', $membership->profile_id)
                ->where(fn ($query) => $query->whereNull('college')->orWhere('college', ''))
                ->update(['college' => $college]);
        }
    }

    public function down(): void
    {
        // Intentionally retain enriched profile data; it may have been
        // corrected or reused after this migration ran.
    }
};
