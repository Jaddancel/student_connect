<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Templates now come in two kinds: 'id' (existing zonal-OCR ID templates) and
 * 'waiver' (event waiver forms whose zones include a stamp/dry-seal region).
 * Existing rows stay 'id'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_templates', function (Blueprint $table) {
            $table->string('kind', 16)->default('id')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('id_templates', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
