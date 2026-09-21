<?php

use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the required flag from the New Event form's Extension Services field
     * for already-seeded databases that were created before the requirement was
     * relaxed.
     */
    public function up(): void
    {
        $formIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_EVENT)
            ->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        DB::table('form_descriptions')
            ->whereIn('form_id', $formIds)
            ->where('field_key', 'extension_services')
            ->update(['is_required' => false]);
    }

    public function down(): void
    {
        // No-op: there is no reliable way to restore a prior user-designed
        // requirement flag for a specific form field after the app has been
        // updated and the relation is now intentionally optional.
    }
};
