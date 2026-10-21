<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requester_id')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('subject', 120);
            $table->text('description');
            $table->string('status', 24)->default('open')->index();
            $table->string('source', 24)->default('assistant');
            $table->string('page_path', 2048)->nullable();
            $table->json('assistant_context')->nullable();
            $table->timestamp('last_response_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('requester_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('body');
            $table->boolean('from_support')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
