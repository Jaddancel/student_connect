<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Members: 3 → 4 first to free up 3 for officers.
        DB::table('users')->where('user_type', 3)->update(['user_type' => 4]);
        // Org officers: 2 → 3. user_type=2 becomes the new system Admin.
        DB::table('users')->where('user_type', 2)->update(['user_type' => 3]);
    }

    public function down(): void
    {
        DB::table('users')->where('user_type', 3)->update(['user_type' => 2]);
        DB::table('users')->where('user_type', 4)->update(['user_type' => 3]);
    }
};
