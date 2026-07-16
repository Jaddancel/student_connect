<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the single "Course & Year" (`course_year`) into two universal-field
 * columns: `course` and `year_section`. Existing `course_year` values are copied
 * into `course` as a best-effort backfill (the combined string can't be reliably
 * parsed into a section), leaving `year_section` for the user to complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('course')->nullable()->after('course_year');
            $table->string('year_section')->nullable()->after('course');
        });

        DB::table('profiles')
            ->whereNotNull('course_year')
            ->whereNull('course')
            ->update(['course' => DB::raw('course_year')]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['course', 'year_section']);
        });
    }
};
