<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_templates', function (Blueprint $table) {
            $table->id('id_template_id');
            $table->string('name', 150);
            $table->string('image_path');
            $table->unsignedInteger('image_width');
            $table->unsignedInteger('image_height');
            $table->json('zones');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_templates');
    }
};
