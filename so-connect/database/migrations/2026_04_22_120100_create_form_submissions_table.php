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
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id('form_submission_id');
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->json('payload');
            $table->dateTime('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index('form_id', 'form_submissions_form_id_idx');
            $table->index('organization_id', 'form_submissions_organization_id_idx');
            $table->index('submitted_by', 'form_submissions_submitted_by_idx');

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnDelete();

            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->nullOnDelete();

            $table->foreign('submitted_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
