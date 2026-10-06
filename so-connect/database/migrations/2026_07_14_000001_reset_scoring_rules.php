<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset every saved scoring trigger. The legacy hardcoded "built-in behavior"
 * was retired in favour of the block-programming editor, so any pre-existing
 * ScoringRule rows carry meaning that no longer applies. Clear them all so each
 * criterion starts truly blank and is scored only by an admin-authored block
 * trigger going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scoring_rules')) {
            DB::table('scoring_rules')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: the previous triggers are intentionally discarded.
    }
};
