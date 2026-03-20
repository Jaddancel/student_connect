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
        Schema::create('document_descriptions', function (Blueprint $table) {
            $table->id('document_desc_id');
            $table->string('document_title');
            $table->text('document_desc_text');
            $table->timestamp('document_created_at');
            $table->timestamp('document_modified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_descriptions');
    }
};
