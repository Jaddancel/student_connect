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
        Schema::create('organization_officers', function (Blueprint $table) {
            $table->id('org_officer_id');
            $table->string('role');
            $table->unsignedBigInteger('organization')->nullable();
            $table->unsignedBigInteger('yearterm')->nullable();
            $table->unsignedBigInteger('member')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('reassigned_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('officers');
    }
};
