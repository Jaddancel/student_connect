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
            $table->id('form_description_id');
            $table->string('form_name', 255);
            $table->text('form_description_text');
            $table->unsignedBigInteger('form_organizations');
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
