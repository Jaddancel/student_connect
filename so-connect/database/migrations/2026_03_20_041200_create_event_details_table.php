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
            $table->text('event_desc_text')->nullable();
            $table->timestamp('event_start_time')->nullable();
            $table->timestamp('event_end_time')->nullable();
            $table->string('event_location')->nullable();

        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('event_detail')
                ->references('event_detail_id')
                ->on('event_details')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['event_detail']);
        });

        Schema::dropIfExists('event_details');
    }
};
