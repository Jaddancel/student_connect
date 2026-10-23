<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->unsignedTinyInteger('level_number');
            $table->string('label', 100);
            $table->decimal('minimum_score', 5, 2);
            $table->string('color', 20)->default('gray');
            $table->timestamps();
            $table->unique(['scope', 'level_number']);
        });

        Schema::create('level_histories', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->foreignId('semester_id')->constrained('semesters', 'semester_id')->cascadeOnDelete();
            $table->unsignedBigInteger('subject_id');
            $table->decimal('raw_score', 8, 2);
            $table->decimal('normalized_score', 5, 2);
            $table->unsignedTinyInteger('level_number');
            $table->string('level_label', 100);
            $table->decimal('threshold_snapshot', 5, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['scope', 'semester_id', 'subject_id']);
            $table->index(['semester_id', 'scope', 'level_number']);
        });

        $now = now();
        DB::table('level_thresholds')->insert([
            ['scope' => 'organization', 'level_number' => 4, 'label' => 'Excellent', 'minimum_score' => 90, 'color' => 'success', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'organization', 'level_number' => 3, 'label' => 'Very Satisfactory', 'minimum_score' => 80, 'color' => 'brand', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'organization', 'level_number' => 2, 'label' => 'Satisfactory', 'minimum_score' => 70, 'color' => 'warning', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'organization', 'level_number' => 1, 'label' => 'Needs Improvement', 'minimum_score' => 0, 'color' => 'gray', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'student', 'level_number' => 4, 'label' => 'Outstanding', 'minimum_score' => 90, 'color' => 'success', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'student', 'level_number' => 3, 'label' => 'Advanced', 'minimum_score' => 80, 'color' => 'brand', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'student', 'level_number' => 2, 'label' => 'Proficient', 'minimum_score' => 70, 'color' => 'warning', 'created_at' => $now, 'updated_at' => $now],
            ['scope' => 'student', 'level_number' => 1, 'label' => 'Developing', 'minimum_score' => 0, 'color' => 'gray', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('level_histories');
        Schema::dropIfExists('level_thresholds');
    }
};
