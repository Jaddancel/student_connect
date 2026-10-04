<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot_order')->default(0)->after('is_active');
            $table->index(['form_id', 'is_active', 'slot_order'], 'templates_active_slots_idx');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex('templates_active_slots_idx');
            $table->dropColumn('slot_order');
        });
    }
};
