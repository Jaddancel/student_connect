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
        Schema::create('members', function (Blueprint $table) {
            $table->id('member_id');
            $table->unsignedBigInteger('user');
            $table->unsignedBigInteger('organization');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('member_since')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('organization')->references('organization_id')->on('organizations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
