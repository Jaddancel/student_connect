<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the WYSIWYG builder layout column.
     *
     * `layout` holds the canvas arrangement (ordered rows/columns, field placement,
     * column spans, section headers, static text blocks) plus an optional `header`
     * key (letterhead image and/or designed logo+title block). The builder writes it;
     * the dynamic renderer and the PDF document view consume it.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
