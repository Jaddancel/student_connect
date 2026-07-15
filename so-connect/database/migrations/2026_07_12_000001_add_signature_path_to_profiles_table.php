<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's signature becomes a universal profile attribute: captured on the
 * profile page, imported from a scanned ID's signature zone, and referenced by
 * signature form fields for autofill/recognition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('signature_path', 2048)->nullable()->after('id_photo_back');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
