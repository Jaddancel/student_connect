<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('forms')
            ->where('route_name', 'project-request')
            ->where('name', 'Letter of Intent / Project Request')
            ->update(['name' => 'Project Request']);
    }

    public function down(): void
    {
        DB::table('forms')
            ->where('route_name', 'project-request')
            ->where('name', 'Project Request')
            ->update(['name' => 'Letter of Intent / Project Request']);
    }
};
