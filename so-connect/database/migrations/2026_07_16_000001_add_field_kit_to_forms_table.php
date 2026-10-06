<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A form's "field kit" unlocks a category of special palette fields that
     * only make sense on that form (e.g. the Financial Report cost tables).
     * System-function forms derive their kit from `system_function`; this
     * column carries the kit for seeded/plain forms that need one.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('field_kit')->nullable()->after('system_function');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('field_kit');
        });
    }
};
