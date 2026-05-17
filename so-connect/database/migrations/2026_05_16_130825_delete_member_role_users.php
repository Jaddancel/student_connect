<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $memberUserIds = DB::table('users')->where('user_type', 4)->pluck('user_id');

        if ($memberUserIds->isEmpty()) {
            return;
        }

        $memberIds = DB::table('members')
            ->whereIn('user', $memberUserIds)
            ->pluck('member_id');

        if ($memberIds->isNotEmpty()) {
            DB::table('organization_officers')->whereIn('member', $memberIds)->delete();
        }

        DB::table('members')->whereIn('user', $memberUserIds)->delete();
        DB::table('requests')->whereIn('user', $memberUserIds)->delete();
        DB::table('users')->whereIn('user_id', $memberUserIds)->delete();
    }

    public function down(): void
    {
        // Not reversible — member role users have been permanently removed
    }
};
