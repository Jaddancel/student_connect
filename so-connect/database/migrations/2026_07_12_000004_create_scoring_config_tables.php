<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable scoring system backing the Scratch-like trigger editor:
 *  - scoring_categories: the six category buckets with their point caps;
 *  - scoring_criteria:   the individual scored conditions (seeded from the
 *    previously hardcoded engine, is_system=true; admins may add their own);
 *  - scoring_rules:      one optional trigger per criterion — the Blockly
 *    workspace (for round-tripping the editor) plus the compiled trigger AST
 *    the server evaluates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_categories', function (Blueprint $table) {
            $table->id('scoring_category_id');
            $table->string('key', 32)->unique();
            $table->string('label');
            $table->unsignedInteger('cap');
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('scoring_criteria', function (Blueprint $table) {
            $table->id('scoring_criterion_id');
            $table->string('key', 64)->unique();
            $table->string('category_key', 32)->index();
            $table->string('label');
            $table->unsignedInteger('weight');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id('scoring_rule_id');
            $table->unsignedBigInteger('criterion_id')->unique();
            $table->json('workspace')->nullable();
            $table->json('trigger');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('criterion_id')
                ->references('scoring_criterion_id')
                ->on('scoring_criteria')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_rules');
        Schema::dropIfExists('scoring_criteria');
        Schema::dropIfExists('scoring_categories');
    }
};
