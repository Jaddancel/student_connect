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
        Schema::create('member_organizations', function (Blueprint $table) {
            $table->id('member_organization_id');
            $table->unsignedBigInteger('member_detail_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('member_organization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_organizations');
    }
};
