<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_descriptions', function (Blueprint $table) {
            // Maps this form field to a code-defined App\Support\UniversalField key
            // so its value can autofill from the signed-in user's profile. Null =
            // an ordinary free-form field with no universal mapping.
            $table->string('universal_key')->nullable()->index()->after('field_options');
        });
    }

    public function down(): void
    {
        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->dropIndex(['universal_key']);
            $table->dropColumn('universal_key');
        });
    }
};
