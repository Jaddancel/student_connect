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
        Schema::create('request_types', function (Blueprint $table) {
            $table->id('request_type_id');
            $table->string('name');
            $table->string('category', 64);
            $table->string('system_key', 64)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'request_types_name_unique');
            $table->unique('system_key', 'request_types_system_key_unique');
            $table->index('category', 'request_types_category_idx');
            $table->index('created_by', 'request_types_created_by_idx');

            $table->foreign('created_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};
