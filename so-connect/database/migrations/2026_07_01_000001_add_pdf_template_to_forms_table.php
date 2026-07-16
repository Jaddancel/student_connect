<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the separate printed-PDF template column.
     *
     * `pdf_template` holds the rich-text document design authored in the wizard's
     * Step 2: `{ html: "<...>", page: { size: 'a4', orientation: 'portrait' } }`.
     * Its HTML carries inline field-token spans (`data-field="{field_key}"`) that
     * {@see \App\Forms\PdfTemplateRenderer} replaces with submission values when
     * generating the output document. Decoupled from `forms.layout`, which drives
     * the online fill-in form.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('pdf_template')->nullable()->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('pdf_template');
        });
    }
};
