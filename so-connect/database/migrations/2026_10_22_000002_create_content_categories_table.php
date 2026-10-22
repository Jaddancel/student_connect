<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->default('brand');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('description_text')
                ->constrained('content_categories')->nullOnDelete();
        });

        Schema::table('report_templates', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('description')
                ->constrained('content_categories')->nullOnDelete();
        });

        $now = now();
        foreach ([
            ['Membership', 'For membership applications, approvals, and member records.', 'user-profile', 'brand'],
            ['Accreditation', 'For organization accreditation and renewal processes.', 'forms', 'brand'],
            ['Activity', 'For activity proposals, work plans, monitoring, and requests.', 'calendar', 'brand'],
            ['Financial', 'For budgets, funding, liquidation, and financial reports.', 'charts', 'brand'],
            ['Accomplishment', 'For after-activity and accomplishment reports.', 'check-circle', 'brand'],
            ['Recognition', 'For recognition and best-organization processes.', 'task', 'brand'],
        ] as $order => [$name, $description, $icon, $color]) {
            DB::table('content_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'is_active' => true,
                'display_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ids = DB::table('content_categories')->pluck('id', 'slug');
        DB::table('forms')->orderBy('id')->get()->each(function ($form) use ($ids) {
            $text = strtolower(trim(($form->system_function ?? '').' '.($form->name ?? '')));
            $slug = match (true) {
                str_contains($text, 'membership'), str_contains($text, 'member') => 'membership',
                str_contains($text, 'accredit') => 'accreditation',
                str_contains($text, 'after_event'), str_contains($text, 'accomplish'), str_contains($text, 'after event') => 'accomplishment',
                str_contains($text, 'financ'), str_contains($text, 'budget'), str_contains($text, 'fund') => 'financial',
                str_contains($text, 'recognition'), str_contains($text, 'best organization') => 'recognition',
                str_contains($text, 'event'), str_contains($text, 'activity'), str_contains($text, 'workplan') => 'activity',
                default => null,
            };
            if ($slug && isset($ids[$slug])) {
                DB::table('forms')->where('id', $form->id)->update(['category_id' => $ids[$slug]]);
            }
        });

        DB::table('report_templates')->orderBy('id')->get()->each(function ($report) use ($ids) {
            $text = strtolower((string) ($report->name ?? ''));
            $slug = match (true) {
                str_contains($text, 'financial'), str_contains($text, 'budget') => 'financial',
                str_contains($text, 'scoring'), str_contains($text, 'ranking') => 'recognition',
                str_contains($text, 'accomplish') => 'accomplishment',
                str_contains($text, 'recognition'), str_contains($text, 'best') => 'recognition',
                str_contains($text, 'member'), str_contains($text, 'officer') => 'membership',
                str_contains($text, 'accredit'), str_contains($text, 'registered organization') => 'accreditation',
                str_contains($text, 'activity'), str_contains($text, 'event') => 'activity',
                default => null,
            };
            if ($slug && isset($ids[$slug])) {
                DB::table('report_templates')->where('id', $report->id)->update(['category_id' => $ids[$slug]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::table('forms', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::dropIfExists('content_categories');
    }
};
