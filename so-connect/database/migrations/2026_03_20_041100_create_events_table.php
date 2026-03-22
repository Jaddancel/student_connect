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
        Schema::create('events', function (Blueprint $table) {
            $table->id('event_id');
            $table->unsignedBigInteger('creator');
            $table->unsignedBigInteger('event_detail')->nullable();
            $table->unsignedBigInteger('organization');
            $table->timestamps();

            // Foreign key
            $table->foreign('creator')->references('member_id')->on('members')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('organization')->references('organization_id')->on('organizations')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
