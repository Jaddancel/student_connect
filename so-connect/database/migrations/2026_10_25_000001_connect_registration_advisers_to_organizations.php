<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Make registration advisers repeatable and connect existing adviser fields to organization data. */
    public function up(): void
    {
        $registrationFormIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        DB::table('form_descriptions')
            ->whereIn('form_id', $registrationFormIds)
            ->where('field_key', 'faculty_adviser')
            ->update([
                'field_label' => 'Proposed Faculty Advisers',
                'field_type' => FieldType::TEXT_LIST,
                'placeholder_hint' => 'Faculty adviser full name',
                'field_options' => json_encode([
                    'first_placeholder' => 'Primary adviser full name',
                    'additional_placeholder' => 'Additional adviser full name',
                    'add_label' => '+ Add another adviser',
                ]),
                'updated_at' => now(),
            ]);

        $this->mapFields(['faculty_advisers', 'adviser_names'], FieldType::TEXT_LIST, 'advisers');
        $this->mapFields(['adviser_name', 'adviser1_name', 'adviser_1_name', 'name_of_the_adviser', 'advisername'], FieldType::TEXT, 'adviser');
        $this->mapFields(['adviser2_name', 'adviser_2_name'], FieldType::TEXT, 'adviser_2');
    }

    public function down(): void
    {
        $registrationFormIds = DB::table('forms')
            ->where('system_function', SystemFunction::NEW_ORGANIZATION_REGISTRATION)
            ->pluck('id');

        DB::table('form_descriptions')
            ->whereIn('form_id', $registrationFormIds)
            ->where('field_key', 'faculty_adviser')
            ->update([
                'field_label' => 'Proposed Faculty Adviser',
                'field_type' => FieldType::TEXT,
                'placeholder_hint' => null,
                'field_options' => null,
                'updated_at' => now(),
            ]);

        DB::table('form_descriptions')
            ->whereIn('field_key', ['faculty_advisers', 'adviser_names'])
            ->where('universal_key', 'advisers')
            ->update(['universal_key' => null]);
        DB::table('form_descriptions')
            ->whereIn('field_key', ['adviser_name', 'adviser1_name', 'adviser_1_name', 'name_of_the_adviser', 'advisername'])
            ->where('universal_key', 'adviser')
            ->update(['universal_key' => null]);
        DB::table('form_descriptions')
            ->whereIn('field_key', ['adviser2_name', 'adviser_2_name'])
            ->where('universal_key', 'adviser_2')
            ->update(['universal_key' => null]);
    }

    /** @param  string[]  $keys */
    private function mapFields(array $keys, string $type, string $universalKey): void
    {
        DB::table('form_descriptions')
            ->whereIn('field_key', $keys)
            ->where('field_type', $type)
            ->whereNull('universal_key')
            ->update([
                'universal_key' => $universalKey,
                'updated_at' => now(),
            ]);
    }
};
