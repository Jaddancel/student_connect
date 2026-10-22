<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rubric_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('content_hash', 64)->unique();
            $table->json('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();
        });

        Schema::table('organization_scores', function (Blueprint $table) {
            $table->foreignId('rubric_version_id')->nullable()->after('semester_id')
                ->constrained('scoring_rubric_versions')->nullOnDelete();
        });

        Schema::create('scoring_evidence_awards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_score_id')->index();
            $table->string('criterion_key', 64)->index();
            $table->string('criterion_label');
            $table->unsignedInteger('instances')->default(0);
            $table->decimal('rule_points', 8, 2)->default(0);
            $table->decimal('awarded_points', 8, 2)->default(0);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('evidence_label');
            $table->string('evidence_url')->nullable();
            $table->json('attachment_paths')->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->string('quality_status', 20)->default('not_applicable');
            $table->json('quality_notes')->nullable();
            $table->string('review_status', 20)->default('pending');
            $table->decimal('override_points', 8, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_score_id')->references('organization_score_id')
                ->on('organization_scores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_evidence_awards');
        Schema::table('organization_scores', fn (Blueprint $table) => $table->dropConstrainedForeignId('rubric_version_id'));
        Schema::dropIfExists('scoring_rubric_versions');
    }
};
