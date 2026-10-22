<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * After Event Reports have always generated their document immediately;
     * now that admins can switch on approval, existing forms keep that behaviour.
     */
    public function up(): void
    {
        DB::table('forms')
            ->where('system_function', 'after_event_report')
            ->update(['auto_approve_submissions' => true]);
    }

    public function down(): void
    {
        DB::table('forms')
            ->where('system_function', 'after_event_report')
            ->update(['auto_approve_submissions' => false]);
    }
};
