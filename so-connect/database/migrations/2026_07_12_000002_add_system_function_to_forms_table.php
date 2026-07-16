<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A form page can be bound to exactly one fixed system function (sign_up,
 * new_event, new_workplan, membership_registration — see
 * App\Forms\SystemFunction). The bound form becomes the document form for
 * that flow and its submissions run through the function's handler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('system_function', 64)->nullable()->unique()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique(['system_function']);
            $table->dropColumn('system_function');
        });
    }
};
