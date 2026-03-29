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
        Schema::create('organization_details', function (Blueprint $table) {
            $table->id('organization_detail_id');
            $table->unsignedBigInteger('president')->nullable();
            $table->string('organization_name');
            $table->string('organization_initials')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('president')->references('member_id')->on('members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_details');
    }
};
