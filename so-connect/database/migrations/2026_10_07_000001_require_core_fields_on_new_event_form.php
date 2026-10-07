<?php

use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restore the New Event form's core submission fields to required after
     * the broad non-special relaxation migration made them optional.
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
            ->whereIn('field_key', ['title', 'target_date', 'event_location', 'event_start_time', 'event_end_time'])
            ->update(['is_required' => true]);
    }

    public function down(): void
    {
        // No-op: the builder's current contract requires these core fields.
    }
};
