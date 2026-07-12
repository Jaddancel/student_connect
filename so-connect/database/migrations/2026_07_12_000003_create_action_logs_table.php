<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator action log: who did what, when, in which category (auth,
 * scoring, scoring_config, form_builder, id_template, …). Superadmins browse
 * and export it; ActionLogger is the single writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_logs', function (Blueprint $table) {
            $table->id('action_log_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('category', 64)->index();
            $table->string('action', 64);
            $table->string('description', 500)->nullable();
            $table->json('meta')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
    }
};
