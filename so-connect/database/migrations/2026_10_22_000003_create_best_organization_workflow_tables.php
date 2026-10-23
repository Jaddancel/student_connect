<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_organization_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->unique()->constrained('semesters', 'semester_id')->cascadeOnDelete();
            $table->string('title');
            $table->string('status', 24)->default('draft');
            $table->text('selection_notes')->nullable();
            $table->unsignedBigInteger('winner_organization_id')->nullable();
            $table->string('winner_name')->nullable();
            $table->decimal('winner_score', 7, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('evaluation_started_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('winner_organization_id')->references('organization_id')->on('organizations')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('finalized_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('best_organization_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('best_organization_cycles')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name_snapshot');
            $table->unsignedBigInteger('organization_score_id')->nullable();
            $table->decimal('score_snapshot', 7, 2);
            $table->unsignedInteger('rank_snapshot');
            $table->string('status', 24)->default('eligible');
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('organization_id')->on('organizations')->nullOnDelete();
            $table->foreign('organization_score_id')->references('organization_score_id')->on('organization_scores')->nullOnDelete();
            $table->foreign('reviewed_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['cycle_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_organization_candidates');
        Schema::dropIfExists('best_organization_cycles');
    }
};
