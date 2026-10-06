<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional cap on how many submissions of a form may be accepted (approved)
 * per semester, across all organizations. Null = unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedInteger('semester_submission_limit')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('semester_submission_limit');
        });
    }
};
