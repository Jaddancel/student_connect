<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_plans', function (Blueprint $table) {
            $table->id('event_plan_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->date('target_date');
            $table->text('resources_needed')->nullable();
            $table->json('persons_responsible')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'junked'])->default('pending');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('organization_id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('event_id')->references('event_id')->on('events')->nullOnDelete();
            $table->foreign('request_id')->references('request_id')->on('requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_plans');
    }
};
