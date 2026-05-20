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
        Schema::create('presidents', function (Blueprint $table) {
            $table->id('president_id');
            $table->unsignedBigInteger('officer')->nullable();
            $table->timestamps();

            $table->foreign('officer')
                ->references('org_officer_id')
                ->on('organization_officers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presidents');
    }
};
