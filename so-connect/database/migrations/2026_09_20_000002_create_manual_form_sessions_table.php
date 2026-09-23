<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable manual-filling sessions ("drafts"): a user starts filling a printed
 * form, the exact partial PDF is frozen, and a returned scan is parsed for
 * review before the ordinary submission runs. Sessions outlive the request and
 * are resumed from the Drafts page (owner) or a private link (public forms).
 * See docs/manual-form-parsing-contract.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_form_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('template_version')->nullable();

            // Owner (null for an unauthenticated public-form session). A public
            // session is instead resumed by matching the hashed resume token.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('public_token_hash')->nullable();

            // preparing | awaiting_scan | parsing | review | failed | submitted | expired
            $table->string('status')->default('preparing');

            // Snapshot of the form values the user had entered at Start time,
            // plus the field manifests classifying paper support.
            $table->json('draft_payload')->nullable();
            $table->json('known_fields')->nullable();
            $table->json('extractable_fields')->nullable();
            $table->json('digital_only_fields')->nullable();
            $table->json('hidden_context')->nullable();

            // Frozen parsing schemas.
            $table->json('baseline_schema')->nullable();
            $table->json('session_schema')->nullable();

            // The exact partial PDF that was printed, and its rasterized pages.
            $table->string('partial_pdf_path')->nullable();
            $table->string('partial_pdf_hash')->nullable();
            $table->json('page_meta')->nullable();

            // The returned handwritten scan(s) and their aligned page images.
            $table->json('scan_paths')->nullable();
            $table->json('aligned_page_paths')->nullable();

            // Parse output surfaced for review (never business-field mixed).
            $table->json('parse_result')->nullable();
            $table->json('parse_confidence')->nullable();
            $table->json('parse_warnings')->nullable();
            $table->string('parse_error')->nullable();
            $table->string('parse_model')->nullable();

            $table->unsignedBigInteger('form_submission_id')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'manual_sessions_user_idx');
            $table->index('status', 'manual_sessions_status_idx');
            $table->index('form_id', 'manual_sessions_form_idx');
            $table->index('expires_at', 'manual_sessions_expires_idx');
            $table->index('created_at', 'manual_sessions_created_idx');

            $table->foreign('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('templates')->nullOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('form_submission_id')
                ->references('form_submission_id')->on('form_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_form_sessions');
    }
};
