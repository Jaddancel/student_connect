<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $formId = DB::table('forms')->where('route_name', 'workplan')->value('id');

        if (! $formId) {
            return;
        }

        $existing = DB::table('form_descriptions')
            ->where('form_id', $formId)
            ->whereIn('field_key', ['people', 'resources'])
            ->pluck('field_key')
            ->all();

        $toInsert = [];

        if (! in_array('resources', $existing, true)) {
            $toInsert[] = [
                'form_id' => $formId,
                'field_key' => 'resources',
                'field_label' => 'Resources Needed',
                'field_type' => 'textarea',
                'is_required' => true,
                'field_order' => 8,
                'placeholder_hint' => null,
                'field_options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! in_array('people', $existing, true)) {
            $toInsert[] = [
                'form_id' => $formId,
                'field_key' => 'people',
                'field_label' => 'Persons Responsible',
                'field_type' => 'text',
                'is_required' => true,
                'field_order' => 9,
                'placeholder_hint' => null,
                'field_options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($toInsert)) {
            DB::table('form_descriptions')->insert($toInsert);
        }
    }

    public function down(): void
    {
        $formId = DB::table('forms')->where('route_name', 'workplan')->value('id');

        if (! $formId) {
            return;
        }

        DB::table('form_descriptions')
            ->where('form_id', $formId)
            ->whereIn('field_key', ['people', 'resources'])
            ->delete();
    }
};
