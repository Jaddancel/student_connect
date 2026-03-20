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
        Schema::create('form_descriptions', function (Blueprint $table) {
            $table->id('form_desc_id');
            $table->string('form_name');
            $table->text('form_desc_text')->nullable();
            $table->string('form_category')->nullable();
            $table->timestamp('form_created_at')->nullable();
            $table->timestamp('form_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_descriptions');
    }
};
