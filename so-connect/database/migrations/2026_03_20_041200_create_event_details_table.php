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
        Schema::create('event_details', function (Blueprint $table) {
            $table->id('event_detail_id');
            $table->unsignedBigInteger('event_id');
            $table->string('event_name');
            $table->text('event_desc_text')->nullable();
            $table->longText('event_desc_html')->nullable();
            $table->timestamp('event_start_time')->nullable();
            $table->timestamp('event_end_time')->nullable();

            // Foreign key
            $table->foreign('event_id')->references('event_id')->on('events')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_details');
    }
};
