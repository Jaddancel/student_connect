<?php

use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('forms')
            ->where('system_function', SystemFunction::NEW_EVENT)
            ->update([
                'name' => 'Event Application',
                'description_text' => 'Apply for a new organization event. OSSD approval schedules it on the calendar.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('forms')
            ->where('system_function', SystemFunction::NEW_EVENT)
            ->update([
                'name' => 'New Event',
                'description_text' => 'Request a new event or activity. An admin approval schedules it on the calendar.',
                'updated_at' => now(),
            ]);
    }
};
