<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_descriptions', function (Blueprint $table) {
            // {"x_pct": 0.1, "y_pct": 0.2, "width_pct": 0.4, "height_pct": 0.05, "page": 1}
            $table->json('ocr_region')->nullable()->after('field_options');
        });
    }

    public function down(): void
    {
        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->dropColumn('ocr_region');
        });
    }
};
