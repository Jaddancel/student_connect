<?php

use App\Models\Form;
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
        Schema::table('forms', function (Blueprint $table) {
            $table->string('sidebar_group', 32)->default(Form::SIDEBAR_GROUP_PRESIDENT)->after('description_text');
            $table->index('sidebar_group', 'forms_sidebar_group_idx');
        });

        \DB::table('forms')
            ->whereNull('sidebar_group')
            ->update(['sidebar_group' => Form::SIDEBAR_GROUP_PRESIDENT]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex('forms_sidebar_group_idx');
            $table->dropColumn('sidebar_group');
        });
    }
};