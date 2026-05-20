<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_scores', function (Blueprint $table) {
            $table->id('organization_score_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('scored_by')->nullable();
            $table->json('payload');
            $table->json('raw_scores');
            $table->decimal('total_weighted_score', 5, 2)->default(0);
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->foreign('semester_id')
                ->references('semester_id')
                ->on('semesters')
                ->cascadeOnDelete();
            $table->foreign('scored_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(['organization_id', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_scores');
    }
};
