<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who may see/generate a report on the Reports page: all users, admins only
 * or organization officers only. Defaults to admins so existing reports keep
 * their previous (admin-only) visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->string('audience', 16)->default('admins')->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
