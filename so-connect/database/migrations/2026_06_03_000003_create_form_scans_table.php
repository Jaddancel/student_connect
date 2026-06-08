<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('scan_image_path', 500);
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->json('ocr_raw')->nullable();      // raw PaddleOCR blocks with bbox
            $table->json('ocr_result')->nullable();   // matched field_key → text
            $table->text('error_message')->nullable();
            $table->string('job_id', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_scans');
    }
};
