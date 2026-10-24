<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Add the founding-team and OSSD document fields to existing system forms. */
    public function up(): void
    {
        $formIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        foreach ($formIds as $formId) {
            DB::table('form_descriptions')
                ->where('form_id', $formId)
                ->where('field_key', 'organization_description')
                ->update([
                    'field_label' => 'Purpose and Objectives',
                    'is_required' => true,
                    'updated_at' => now(),
                ]);

            $nextOrder = (int) DB::table('form_descriptions')
                ->where('form_id', $formId)
                ->max('field_order');

            foreach ($this->fields() as $field) {
                $exists = DB::table('form_descriptions')
                    ->where('form_id', $formId)
                    ->where('field_key', $field['field_key'])
                    ->exists();

                if (! $exists) {
                    DB::table('form_descriptions')->insert($field + [
                        'form_id' => $formId,
                        'field_order' => ++$nextOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $form = DB::table('forms')->where('id', $formId)->first(['layout']);
            $layout = json_decode((string) ($form?->layout ?? ''), true);
            $layout = is_array($layout) ? $layout : [];
            $rows = is_array($layout['rows'] ?? null) ? $layout['rows'] : [];
            $placed = collect($rows)
                ->flatMap(fn (array $row) => $row['columns'] ?? [])
                ->flatMap(fn (array $column) => $column['fields'] ?? [])
                ->map('strval')
                ->all();

            foreach (array_column($this->fields(), 'field_key') as $key) {
                if (! in_array($key, $placed, true)) {
                    $rows[] = ['columns' => [['span' => 12, 'fields' => [$key]]]];
                }
            }

            $layout['rows'] = $rows;
            DB::table('forms')->where('id', $formId)->update([
                'description_text' => 'Apply for OSSD recognition of a brand-new student organization.',
                'layout' => json_encode($layout),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $formIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        DB::table('form_descriptions')
            ->whereIn('form_id', $formIds)
            ->whereIn('field_key', array_column($this->fields(), 'field_key'))
            ->delete();
    }

    /** @return array<int,array{field_key:string,field_label:string,field_type:string,is_required:bool,field_options:?string}> */
    private function fields(): array
    {
        return [
            ['field_key' => 'target_membership', 'field_label' => 'Target Membership', 'field_type' => FieldType::TEXTAREA, 'is_required' => true, 'field_options' => json_encode(['rows' => 3])],
            ['field_key' => 'faculty_adviser', 'field_label' => 'Proposed Faculty Adviser', 'field_type' => FieldType::TEXT, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'constitution_and_bylaws', 'field_label' => 'Constitution and By-Laws', 'field_type' => FieldType::FILE, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'founding_members_list', 'field_label' => 'List of Founding Members', 'field_type' => FieldType::FILE, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'proposed_officers_list', 'field_label' => 'List of Proposed Officers', 'field_type' => FieldType::FILE, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'faculty_adviser_acceptance', 'field_label' => 'Faculty Adviser Acceptance', 'field_type' => FieldType::FILE, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'organization_logo', 'field_label' => 'Organization Logo', 'field_type' => FieldType::IMAGE, 'is_required' => true, 'field_options' => null],
            ['field_key' => 'gpoa_budget', 'field_label' => 'General Plan of Action (GPOA) with Proposed Budget', 'field_type' => FieldType::FILE, 'is_required' => true, 'field_options' => null],
        ];
    }
};
