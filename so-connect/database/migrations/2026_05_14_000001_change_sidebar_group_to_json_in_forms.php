<?php

use App\Models\Form;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new JSON column alongside the old one
        Schema::table('forms', function (Blueprint $table) {
            $table->json('sidebar_group_new')->nullable()->after('sidebar_group');
        });

        // Migrate existing single-value strings into JSON arrays
        DB::statement("UPDATE forms SET sidebar_group_new = JSON_ARRAY(COALESCE(sidebar_group, 'president'))");

        // Drop old string column and its index
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex('forms_sidebar_group_idx');
            $table->dropColumn('sidebar_group');
        });

        // Rename new column
        Schema::table('forms', function (Blueprint $table) {
            $table->renameColumn('sidebar_group_new', 'sidebar_group');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('sidebar_group_old', 32)->nullable()->after('sidebar_group');
        });

        DB::statement("UPDATE forms SET sidebar_group_old = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(sidebar_group, '$[0]')), 'president')");

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('sidebar_group');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->renameColumn('sidebar_group_old', 'sidebar_group');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->string('sidebar_group', 32)->nullable(false)->default(Form::ROLE_LEVEL_PRESIDENT)->change();
            $table->index('sidebar_group', 'forms_sidebar_group_idx');
        });
    }
};
