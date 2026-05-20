<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->tinyInteger('semester_number')->unsigned()->nullable()->after('name');
        });

        // Backfill existing rows based on start month
        DB::statement("
            UPDATE semesters
            SET semester_number = CASE
                WHEN MONTH(starts_at) >= 8 THEN 1
                ELSE 2
            END
            WHERE semester_number IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('semester_number');
        });
    }
};
