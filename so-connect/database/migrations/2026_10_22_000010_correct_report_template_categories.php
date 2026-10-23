<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_categories') || ! Schema::hasColumn('report_templates', 'category_id')) {
            return;
        }

        $categoryIds = DB::table('content_categories')->pluck('id', 'slug');
        $assignments = [
            'membership' => [
                'Organization Officers',
                'Members of Accredited Organizations',
                'Officers of Accredited Organizations',
            ],
            'accreditation' => [
                'Registered Organizations',
                'Accredited School Organizations',
            ],
            'financial' => ['Financial Report'],
            'recognition' => ['Organization Scoring Report'],
        ];

        foreach ($assignments as $slug => $reportNames) {
            if (! isset($categoryIds[$slug])) {
                continue;
            }

            DB::table('report_templates')
                ->whereIn('name', $reportNames)
                ->update([
                    'category_id' => $categoryIds[$slug],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Category corrections are intentionally retained to avoid restoring
        // the former broad and incorrect "organization = accreditation" rule.
    }
};
