<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_records', function (Blueprint $table) {
            $table->id();
            $table->string('submitter_name', 255)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('form_submission_id')->nullable();
            $table->string('signature_path', 500);
            $table->string('perceptual_hash', 64);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('form_submission_id')->references('form_submission_id')->on('form_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_records');
    }
};
