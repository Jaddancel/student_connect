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
        Schema::create('forms', function (Blueprint $table) {
            $table->id('form_id');
            $table->unsignedBigInteger('form_template');
            $table->unsignedBigInteger('form_description');
            $table->string('form_link')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('form_template')->references('template_id')->on('templates')->onDelete('cascade');
            $table->foreign('form_description')->references('form_desc_id')->on('form_descriptions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
