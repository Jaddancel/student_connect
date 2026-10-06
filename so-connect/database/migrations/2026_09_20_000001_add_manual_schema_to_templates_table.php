<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline manual-filling parsing schema for each printed template. Generated
 * once per explicit Form Builder save (after the Step 2 draft is adopted), it
 * records the field metadata and VLM-located writable areas the manual-parsing
 * feature reuses. See docs/manual-form-parsing-contract.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('manual_schema')->nullable();
            $table->string('manual_schema_status')->nullable();
            $table->text('manual_schema_error')->nullable();
            $table->unsignedInteger('manual_schema_template_version')->nullable();
            $table->timestamp('manual_schema_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'manual_schema',
                'manual_schema_status',
                'manual_schema_error',
                'manual_schema_template_version',
                'manual_schema_generated_at',
            ]);
        });
    }
};
