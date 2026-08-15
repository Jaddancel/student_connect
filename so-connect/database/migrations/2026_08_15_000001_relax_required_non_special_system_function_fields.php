<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Relax required-ness on NON-special fields of every system-function form
     * except Sign Up. These requirements were blocking submission flows (e.g.
     * the New Event calendar drawer). Sign Up keeps its requirements, and
     * special fields (org/position/workplan pickers, computed values, ID/waiver
     * scans, etc.) keep theirs so their own flows stay intact.
     *
     * Mirrors the seeder defaults in FormPagesSeeder so existing databases match
     * a fresh seed without a destructive migrate:fresh.
     */
    public function up(): void
    {
        $formIds = DB::table('forms')
            ->whereNotNull('system_function')
            ->where('system_function', '!=', SystemFunction::SIGN_UP)
            ->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        DB::table('form_descriptions')
            ->whereIn('form_id', $formIds)
            ->whereNotIn('field_type', FieldType::specialTypes())
            ->update(['is_required' => false]);
    }

    public function down(): void
    {
        // One-way data relaxation. Required flags are re-established by the
        // seeder (FormPagesSeeder), not by reversing this migration.
    }
};
