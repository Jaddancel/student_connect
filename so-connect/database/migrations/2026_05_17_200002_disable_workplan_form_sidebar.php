<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('forms')->where('route_name', 'workplan')->update(['is_published' => false]);
    }

    public function down(): void
    {
        DB::table('forms')->where('route_name', 'workplan')->update(['is_published' => true]);
    }
};
