<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PDFs are now rendered from a form's layout, not a DOCX template, so a
     * generated document no longer has an originating template or DOCX file.
     */
    public function up(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->change();
            $table->string('docx_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
            $table->string('docx_path')->nullable(false)->change();
        });
    }
};
