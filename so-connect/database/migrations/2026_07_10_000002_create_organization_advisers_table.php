<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization list of advisers backing the "Advisers" universal field. The
 * field renders as a dropdown of the org's known advisers; typing a new name on
 * a submission upserts a row here so it's offered next time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_advisers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('name');
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_advisers');
    }
};
