<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropForeign(['admin']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('approvals')->where('admin', 0)->exists()) {
            throw new \RuntimeException('Cannot restore the approvals.admin foreign key while system approvals exist.');
        }

        Schema::table('approvals', function (Blueprint $table) {
            $table->foreign('admin')->references('user_id')->on('users')->onDelete('set null');
        });
    }
};
