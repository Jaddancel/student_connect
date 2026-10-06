<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a published form is listed in the officer sidebar's "Organization
 * Forms" group. Hidden forms stay reachable from the /forms directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('show_in_sidebar')->default(true)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('show_in_sidebar');
        });
    }
};
