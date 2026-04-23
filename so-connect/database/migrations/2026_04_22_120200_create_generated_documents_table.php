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
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id('generated_document_id');
            $table->unsignedBigInteger('form_submission_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('request_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->string('docx_path');
            $table->string('pdf_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();

            $table->index('form_submission_id', 'generated_documents_submission_id_idx');
            $table->index('template_id', 'generated_documents_template_id_idx');
            $table->index('request_id', 'generated_documents_request_id_idx');
            $table->index('document_id', 'generated_documents_document_id_idx');
            $table->index('generated_by', 'generated_documents_generated_by_idx');

            $table->foreign('form_submission_id')
                ->references('form_submission_id')
                ->on('form_submissions')
                ->cascadeOnDelete();

            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->cascadeOnDelete();

            $table->foreign('request_id')
                ->references('request_id')
                ->on('requests')
                ->nullOnDelete();

            $table->foreign('document_id')
                ->references('document_id')
                ->on('documents')
                ->nullOnDelete();

            $table->foreign('generated_by')
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
        Schema::dropIfExists('generated_documents');
    }
};
