<?php

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
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_id')->nullable()->after('request_id');
            $table->foreign('approval_id')->references('approval_id')->on('approvals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
            $table->dropColumn('approval_id');
        });
    }
};
