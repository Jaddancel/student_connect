<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_waiver_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->unique();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('decision', 20);
            $table->text('remarks')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();

            $table->foreign('request_id')
                ->references('request_id')
                ->on('requests')
                ->cascadeOnDelete();

            $table->foreign('reviewer_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_waiver_reviews');
    }
};
