<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accomplishment_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_submission_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('file_path');
            $table->string('activity_title')->default('');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accomplishment_media');
    }
};
