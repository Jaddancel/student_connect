<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->string('event_location')->nullable()->after('persons_responsible');
            $table->datetime('event_start_time')->nullable()->after('event_location');
            $table->datetime('event_end_time')->nullable()->after('event_start_time');
            $table->text('event_description')->nullable()->after('event_end_time');
            $table->unsignedBigInteger('parent_plan_id')->nullable()->after('request_id');

            $table->foreign('parent_plan_id')
                ->references('event_plan_id')
                ->on('event_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->dropForeign(['parent_plan_id']);
            $table->dropColumn(['event_location', 'event_start_time', 'event_end_time', 'event_description', 'parent_plan_id']);
        });
    }
};
