<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signature reference registry — every known signature the verifier compares
 * against, decoupled from profiles.signature_path so an owner need not be a
 * user (auto-enrolled signatures). Profile signatures are mirrored in here by a
 * backfill / on-write sync; unrecognized signatures are enrolled under a name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_references', function (Blueprint $table) {
            $table->id('reference_id');
            $table->string('name');
            $table->string('signature_path');
            // SigNet embedding (nullable — populated when the sidecar returns one).
            $table->json('embedding')->nullable();
            // 'profile' (mirrors a user profile) or 'enrolled' (owner may be a non-user).
            $table->string('source', 32)->default('enrolled');
            $table->unsignedBigInteger('profile_id')->nullable()->unique();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_references');
    }
};
