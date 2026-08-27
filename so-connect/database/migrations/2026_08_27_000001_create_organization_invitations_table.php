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
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token_hash')->unique();
            $table->foreignId('organization_id')->constrained('organizations', 'organization_id')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('requests', 'request_id')->nullOnDelete();
            $table->string('role')->default('officer');
            $table->string('position')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
