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
            $table->id('document_description_id');
            $table->string('document_title');
            $table->string('document_description');
            $table->unsignedBigInteger('document_author');
            $table->timestamp('document_created_at')->nullable();
            $table->timestamp('document_updated_at')->nullable();
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
