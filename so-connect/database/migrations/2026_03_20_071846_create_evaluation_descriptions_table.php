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
        Schema::create('evaluation_descriptions', function (Blueprint $table) {
            $table->id('evaluation_description_id');
            $table->text('evaluation_description_text')->nullable();
            $table->timestamp('evaluation_created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_descriptions');
    }
};
