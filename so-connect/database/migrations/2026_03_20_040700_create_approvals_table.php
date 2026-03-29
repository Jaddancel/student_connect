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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id('approval_id');
            $table->unsignedBigInteger('admin');
            $table->unsignedBigInteger('request');
            $table->timestamp('approval_timestamp')->nullable();

            // Foreign keys
            $table->foreign('admin')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('request')->references('request_id')->on('requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
