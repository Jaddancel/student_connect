<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Synchronize existing system form definitions with the member-count fields. */
    public function up(): void
    {
        $accreditationFormIds = DB::table('forms')
            ->where('system_function', SystemFunction::ORG_ACCREDITATION)
            ->pluck('id');

        if ($accreditationFormIds->isNotEmpty()) {
            DB::table('form_descriptions')
                ->whereIn('form_id', $accreditationFormIds)
                ->update(['is_required' => false]);
        }

        $registrationFormIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        foreach ($registrationFormIds as $formId) {
            $nextOrder = (int) DB::table('form_descriptions')
                ->where('form_id', $formId)
                ->max('field_order');

            foreach ($this->memberFields() as $field) {
                $existing = DB::table('form_descriptions')
                    ->where('form_id', $formId)
                    ->where('field_key', $field['field_key'])
                    ->first();

                if ($existing === null) {
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
            $placedKeys = collect($rows)
                ->flatMap(fn (array $row) => $row['columns'] ?? [])
                ->flatMap(fn (array $column) => $column['fields'] ?? [])
                ->map('strval')
                ->all();

            foreach (['freshman', 'sophomore', 'junior', 'total'] as $key) {
                if (! in_array($key, $placedKeys, true)) {
                    $rows[] = ['columns' => [['span' => 12, 'fields' => [$key]]]];
                }
            }

            DB::table('forms')->where('id', $formId)->update([
                'layout' => json_encode(['rows' => $rows]),
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
            ->whereIn('field_key', ['freshman', 'sophomore', 'junior', 'total'])
            ->delete();
    }

    /** @return array<int,array{field_key:string,field_label:string,field_type:string,is_required:bool,field_options:?string}> */
    private function memberFields(): array
    {
        return [
            ['field_key' => 'freshman', 'field_label' => 'No. of Freshman Members', 'field_type' => FieldType::NUMBER, 'is_required' => false, 'field_options' => null],
            ['field_key' => 'sophomore', 'field_label' => 'No. of Sophomore Members', 'field_type' => FieldType::NUMBER, 'is_required' => false, 'field_options' => null],
            ['field_key' => 'junior', 'field_label' => 'No. of Junior Members', 'field_type' => FieldType::NUMBER, 'is_required' => false, 'field_options' => null],
            ['field_key' => 'total', 'field_label' => 'Total Members', 'field_type' => FieldType::COMPUTED, 'is_required' => false, 'field_options' => json_encode([
                'formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior'],
            ])],
        ];
    }
};