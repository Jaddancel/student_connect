<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which concluded events have had their "after-event report due"
 * email sent, so `after-event:notify` emails each event's officials once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('after_event_report_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->foreign('event_id')->references('event_id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('after_event_report_notifications');
    }
};
