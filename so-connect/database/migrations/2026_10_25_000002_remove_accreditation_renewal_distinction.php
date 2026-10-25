<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $forms = DB::table('forms')
            ->where('system_function', SystemFunction::ORG_ACCREDITATION)
            ->get(['id', 'layout']);

        foreach ($forms as $form) {
            DB::table('form_descriptions')
                ->where('form_id', $form->id)
                ->where('field_key', 'recognition_type')
                ->delete();

            DB::table('forms')->where('id', $form->id)->update([
                'description_text' => 'Apply for organization accreditation for the next academic year.',
                'layout' => json_encode($this->withoutRecognitionType($form->layout)),
                'updated_at' => now(),
            ]);
        }

        DB::table('content_categories')
            ->where('name', 'Accreditation')
            ->where('description', 'For organization accreditation and renewal processes.')
            ->update([
                'description' => 'For organization accreditation processes.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $forms = DB::table('forms')
            ->where('system_function', SystemFunction::ORG_ACCREDITATION)
            ->get(['id', 'layout']);

        foreach ($forms as $form) {
            $exists = DB::table('form_descriptions')
                ->where('form_id', $form->id)
                ->where('field_key', 'recognition_type')
                ->exists();

            if (! $exists) {
                DB::table('form_descriptions')->insert([
                    'form_id' => $form->id,
                    'field_key' => 'recognition_type',
                    'field_label' => 'Accreditation / Renewal',
                    'field_type' => FieldType::RADIO,
                    'is_required' => false,
                    'field_order' => ((int) DB::table('form_descriptions')->where('form_id', $form->id)->max('field_order')) + 1,
                    'placeholder_hint' => null,
                    'field_options' => json_encode(['options' => [
                        ['value' => 'recognition', 'label' => 'Accreditation'],
                        ['value' => 'renewal', 'label' => 'Renewal'],
                    ]]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $layout = json_decode((string) ($form->layout ?? ''), true);
            $layout = is_array($layout) ? $layout : [];
            $rows = is_array($layout['rows'] ?? null) ? $layout['rows'] : [];
            $placed = collect($rows)
                ->flatMap(fn (array $row) => $row['columns'] ?? [])
                ->flatMap(fn (array $column) => $column['fields'] ?? [])
                ->contains('recognition_type');

            if (! $placed) {
                $rows[] = ['columns' => [['span' => 12, 'fields' => ['recognition_type']]]];
            }

            $layout['rows'] = $rows;
            DB::table('forms')->where('id', $form->id)->update([
                'description_text' => 'Apply for organization accreditation or renewal.',
                'layout' => json_encode($layout),
                'updated_at' => now(),
            ]);
        }

        DB::table('content_categories')
            ->where('name', 'Accreditation')
            ->where('description', 'For organization accreditation processes.')
            ->update([
                'description' => 'For organization accreditation and renewal processes.',
                'updated_at' => now(),
            ]);
    }

    /** @return array<string, mixed> */
    private function withoutRecognitionType(mixed $encodedLayout): array
    {
        $layout = json_decode((string) ($encodedLayout ?? ''), true);
        $layout = is_array($layout) ? $layout : [];
        $rows = is_array($layout['rows'] ?? null) ? $layout['rows'] : [];

        foreach ($rows as &$row) {
            $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];

            foreach ($columns as &$column) {
                $column['fields'] = array_values(array_filter(
                    is_array($column['fields'] ?? null) ? $column['fields'] : [],
                    fn (mixed $field): bool => $field !== 'recognition_type'
                ));
            }
            unset($column);

            $row['columns'] = array_values(array_filter(
                $columns,
                fn (array $column): bool => ($column['fields'] ?? []) !== []
            ));
        }
        unset($row);

        $layout['rows'] = array_values(array_filter(
            $rows,
            fn (array $row): bool => ($row['columns'] ?? []) !== []
                || trim((string) ($row['header'] ?? '')) !== ''
                || trim((string) ($row['static_text'] ?? '')) !== ''
        ));

        return $layout;
    }
};
