<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get workplan form id
        $formId = DB::table('forms')->where('route_name', 'workplan')->value('id');

        if (! $formId) {
            return;
        }

        // Rename camelCase keys to lowercase to match template placeholders
        $renames = [
            'schoolYear'       => 'schoolyear',
            'adviserName'      => 'advisername',
            'adviserSignature' => 'advisersignature',
            'targetDate'       => 'target',
        ];

        foreach ($renames as $old => $new) {
            DB::table('form_descriptions')
                ->where('form_id', $formId)
                ->where('field_key', $old)
                ->update(['field_key' => $new]);
        }

        // Insert target and ensure resources/people exist with correct keys
        $existing = DB::table('form_descriptions')
            ->where('form_id', $formId)
            ->pluck('field_key')
            ->all();

        $maxOrder = DB::table('form_descriptions')
            ->where('form_id', $formId)
            ->max('field_order') ?? 0;

        $toInsert = [
            'target' => ['field_label' => 'Target Date',      'field_type' => 'text',     'is_required' => true],
        ];

        foreach ($toInsert as $key => $attrs) {
            if (! in_array($key, $existing, true)) {
                DB::table('form_descriptions')->insert(array_merge([
                    'form_id'     => $formId,
                    'field_key'   => $key,
                    'field_order' => ++$maxOrder,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ], $attrs));
            }
        }
    }

    public function down(): void
    {
        $formId = DB::table('forms')->where('route_name', 'workplan')->value('id');

        if (! $formId) {
            return;
        }

        $renames = [
            'schoolyear'       => 'schoolYear',
            'advisername'      => 'adviserName',
            'advisersignature' => 'adviserSignature',
        ];

        foreach ($renames as $old => $new) {
            DB::table('form_descriptions')
                ->where('form_id', $formId)
                ->where('field_key', $old)
                ->update(['field_key' => $new]);
        }

        DB::table('form_descriptions')
            ->where('form_id', $formId)
            ->where('field_key', 'target')
            ->delete();
    }
};
