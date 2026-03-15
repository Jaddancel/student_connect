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
            $table->string('event_name');
            $table->timestamp('event_created_at')->useCurrent();
            $table->dateTime('event_start_date');
            $table->dateTime('event_end_date');
            $table->text('event_description_text')->nullable();
            $table->string('event_location')->nullable();
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
