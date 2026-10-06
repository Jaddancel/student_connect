<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-form sidebar icon. Stores a MenuHelper icon key (e.g. "task", "charts");
 * null falls back to the default forms glyph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('icon', 64)->nullable()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
