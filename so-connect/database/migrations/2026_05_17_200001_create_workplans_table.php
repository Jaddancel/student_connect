<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workplans', function (Blueprint $table) {
            $table->id('workplan_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('semester_id');
            $table->enum('status', ['active', 'finalized', 'archived'])->default('active');
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('organization_id')->on('organizations')->cascadeOnDelete();
            $table->foreign('semester_id')->references('semester_id')->on('semesters')->cascadeOnDelete();
            $table->foreign('finalized_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['organization_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workplans');
    }
};
