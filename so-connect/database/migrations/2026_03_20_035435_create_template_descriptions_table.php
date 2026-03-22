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
        Schema::create('template_descriptions', function (Blueprint $table) {
            $table->id('template_desc_id');
            $table->string('template_name');
            $table->text('template_desc')->nullable();
            $table->timestamp('template_created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_descriptions');
    }
};
