<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->text('purpose_of_activity')->nullable()->after('resources_needed');
            $table->string('time_of_activity')->nullable()->after('purpose_of_activity');
            $table->string('place_venue')->nullable()->after('time_of_activity');
            $table->json('university_facilities')->nullable()->after('place_venue');
            $table->string('president_name')->nullable()->after('university_facilities');
            $table->string('president_contact')->nullable()->after('president_name');
            $table->json('faculty_advisers')->nullable()->after('president_contact');
            $table->string('college_dean')->nullable()->after('faculty_advisers');
            $table->json('activity_types')->nullable()->after('college_dean');
            $table->string('activity_types_other')->nullable()->after('activity_types');
            $table->string('area_scope')->nullable()->after('activity_types_other');
            $table->string('area_scope_other')->nullable()->after('area_scope');
            $table->string('sponsor')->nullable()->after('area_scope_other');
            $table->string('sponsor_other')->nullable()->after('sponsor');
            $table->boolean('extension_services')->nullable()->after('sponsor_other');
        });
    }

    public function down(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->dropColumn([
                'purpose_of_activity',
                'time_of_activity',
                'place_venue',
                'university_facilities',
                'president_name',
                'president_contact',
                'faculty_advisers',
                'college_dean',
                'activity_types',
                'activity_types_other',
                'area_scope',
                'area_scope_other',
                'sponsor',
                'sponsor_other',
                'extension_services',
            ]);
        });
    }
};
