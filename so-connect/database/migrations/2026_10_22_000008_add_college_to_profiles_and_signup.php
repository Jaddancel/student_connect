<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy value 6 combined all student governments. Preserve the
        // Supreme Student Council at 6 and move college councils to the new 7.
        $collegeCouncilIds = DB::table('organizations as o')
            ->join('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_type', 6)
            ->where('od.name', 'not like', '%Supreme Student Council%')
            ->pluck('o.organization_id');
        if ($collegeCouncilIds->isNotEmpty()) {
            DB::table('organizations')->whereIn('organization_id', $collegeCouncilIds)->update(['organization_type' => 7]);
        }

        if (! Schema::hasColumn('profiles', 'college')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('college')->nullable()->after('course');
            });
        }

        $forms = DB::table('forms')->where('system_function', SystemFunction::SIGN_UP)->get();
        foreach ($forms as $form) {
            if (DB::table('form_descriptions')->where('form_id', $form->id)->where('field_key', 'college')->exists()) {
                continue;
            }

            $courseOrder = DB::table('form_descriptions')
                ->where('form_id', $form->id)
                ->where('field_key', 'course')
                ->value('field_order');
            $order = $courseOrder !== null
                ? (int) $courseOrder + 1
                : (int) DB::table('form_descriptions')->where('form_id', $form->id)->max('field_order') + 1;

            DB::table('form_descriptions')
                ->where('form_id', $form->id)
                ->where('field_order', '>=', $order)
                ->increment('field_order');

            DB::table('form_descriptions')->insert([
                'form_id' => $form->id,
                'field_key' => 'college',
                'field_label' => 'College',
                'field_type' => FieldType::TEXT,
                'is_required' => true,
                'field_order' => $order,
                'placeholder_hint' => 'e.g. College of Arts and Sciences',
                'field_options' => null,
                'universal_key' => 'college',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $layout = is_string($form->layout ?? null) ? json_decode($form->layout, true) : (array) ($form->layout ?? []);
            $rows = (array) ($layout['rows'] ?? []);
            $insertAt = count($rows);
            foreach ($rows as $index => $row) {
                $keys = collect((array) ($row['columns'] ?? []))->flatMap(fn ($column) => (array) ($column['fields'] ?? []));
                if ($keys->contains('course')) {
                    $insertAt = $index + 1;
                    break;
                }
            }
            array_splice($rows, $insertAt, 0, [[
                'columns' => [['span' => 12, 'fields' => ['college']]],
            ]]);
            $layout['rows'] = $rows;
            DB::table('forms')->where('id', $form->id)->update(['layout' => json_encode($layout), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('organizations')->where('organization_type', 7)->update(['organization_type' => 6]);

        $forms = DB::table('forms')->where('system_function', SystemFunction::SIGN_UP)->get();
        foreach ($forms as $form) {
            DB::table('form_descriptions')->where('form_id', $form->id)->where('field_key', 'college')->delete();
            $layout = is_string($form->layout ?? null) ? json_decode($form->layout, true) : (array) ($form->layout ?? []);
            $layout['rows'] = array_values(array_filter((array) ($layout['rows'] ?? []), function ($row): bool {
                $keys = collect((array) ($row['columns'] ?? []))->flatMap(fn ($column) => (array) ($column['fields'] ?? []));

                return ! $keys->contains('college');
            }));
            DB::table('forms')->where('id', $form->id)->update(['layout' => json_encode($layout), 'updated_at' => now()]);
        }

        if (Schema::hasColumn('profiles', 'college')) {
            Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('college'));
        }
    }
};
