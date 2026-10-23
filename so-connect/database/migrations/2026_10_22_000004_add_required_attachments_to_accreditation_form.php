<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string,string> */
    private array $requirements = [
        'application_letter' => 'Application Letter',
        'constitution_and_bylaws' => 'Constitution and By-Laws',
        'officers_list' => 'List of Officers',
        'members_list' => 'List of Members',
        'adviser_endorsement' => 'Faculty Adviser Endorsement',
        'annual_activity_plan' => 'Annual Activity Plan / Calendar',
        'financial_plan' => 'Financial Statement / Proposed Budget',
    ];

    public function up(): void
    {
        $forms = DB::table('forms')->where('system_function', SystemFunction::ORG_ACCREDITATION)->get();

        foreach ($forms as $form) {
            $order = (int) DB::table('form_descriptions')->where('form_id', $form->id)->max('field_order');
            $layout = is_string($form->layout ?? null) ? json_decode($form->layout, true) : (array) ($form->layout ?? []);
            $rows = (array) ($layout['rows'] ?? []);

            foreach ($this->requirements as $key => $label) {
                $exists = DB::table('form_descriptions')
                    ->where('form_id', $form->id)
                    ->where('field_key', $key)
                    ->exists();

                if (! $exists) {
                    DB::table('form_descriptions')->insert([
                        'form_id' => $form->id,
                        'field_key' => $key,
                        'field_label' => $label,
                        'field_type' => FieldType::FILE,
                        'is_required' => true,
                        'field_order' => ++$order,
                        'placeholder_hint' => null,
                        'field_options' => json_encode(['max_kb' => 10240]),
                        'universal_key' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $rows[] = ['columns' => [['span' => 12, 'fields' => [$key]]]];
                }
            }

            $layout['rows'] = $rows;
            DB::table('forms')->where('id', $form->id)->update([
                'layout' => json_encode($layout),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $forms = DB::table('forms')
            ->where('system_function', SystemFunction::ORG_ACCREDITATION)
            ->get();

        DB::table('form_descriptions')
            ->whereIn('form_id', $forms->pluck('id'))
            ->whereIn('field_key', array_keys($this->requirements))
            ->delete();

        foreach ($forms as $form) {
            $layout = is_string($form->layout ?? null) ? json_decode($form->layout, true) : (array) ($form->layout ?? []);
            $layout['rows'] = array_values(array_filter(
                (array) ($layout['rows'] ?? []),
                function ($row): bool {
                    $keys = collect((array) ($row['columns'] ?? []))
                        ->flatMap(fn ($column) => (array) ($column['fields'] ?? []));

                    return $keys->intersect(array_keys($this->requirements))->isEmpty();
                },
            ));

            DB::table('forms')->where('id', $form->id)->update([
                'layout' => json_encode($layout),
                'updated_at' => now(),
            ]);
        }
    }
};
