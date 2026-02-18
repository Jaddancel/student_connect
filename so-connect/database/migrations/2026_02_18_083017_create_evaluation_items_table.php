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
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->id('evaluation_item_id');
            $table->string('evaluation_description');
            $table->unsignedTinyInteger('evaluation_grade');
            $table->timestamp('evaluation_created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
