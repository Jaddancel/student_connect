<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $removals = [
            'workplan'              => ['advisersignature', 'adviserSignature'],
            'accomplishment-report' => ['signature2'],
            'financial-report'      => ['adviserSig'],
        ];

        foreach ($removals as $routeName => $keys) {
            $formId = DB::table('forms')->where('route_name', $routeName)->value('id');
            if ($formId) {
                DB::table('form_descriptions')
                    ->where('form_id', $formId)
                    ->whereIn('field_key', $keys)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Reversing would require re-inserting rows; omitted intentionally.
    }
};
