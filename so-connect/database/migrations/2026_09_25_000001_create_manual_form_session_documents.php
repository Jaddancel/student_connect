<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_form_session_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('manual_form_session_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('preparing');
            $table->json('baseline_schema')->nullable();
            $table->json('session_schema')->nullable();
            $table->string('partial_pdf_path')->nullable();
            $table->string('partial_pdf_hash')->nullable();
            $table->json('page_meta')->nullable();
            $table->json('scan_paths')->nullable();
            $table->json('aligned_page_paths')->nullable();
            $table->json('parse_result')->nullable();
            $table->json('parse_confidence')->nullable();
            $table->json('parse_warnings')->nullable();
            $table->string('parse_error')->nullable();
            $table->string('parse_model')->nullable();
            $table->timestamps();
            $table->unique(['manual_form_session_id', 'position'], 'manual_document_position_unique');
            $table->foreign('manual_form_session_id')->references('id')->on('manual_form_sessions')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('templates')->nullOnDelete();
        });

        DB::table('manual_form_sessions')->orderBy('id')->chunk(100, function ($sessions) {
            foreach ($sessions as $session) {
                DB::table('manual_form_session_documents')->insert([
                    'manual_form_session_id' => $session->id,
                    'template_id' => $session->template_id,
                    'template_version' => $session->template_version,
                    'position' => 0,
                    'status' => $session->status,
                    'baseline_schema' => $session->baseline_schema,
                    'session_schema' => $session->session_schema,
                    'partial_pdf_path' => $session->partial_pdf_path,
                    'partial_pdf_hash' => $session->partial_pdf_hash,
                    'page_meta' => $session->page_meta,
                    'scan_paths' => $session->scan_paths,
                    'aligned_page_paths' => $session->aligned_page_paths,
                    'parse_result' => $session->parse_result,
                    'parse_confidence' => $session->parse_confidence,
                    'parse_warnings' => $session->parse_warnings,
                    'parse_error' => $session->parse_error,
                    'parse_model' => $session->parse_model,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_form_session_documents');
    }
};
