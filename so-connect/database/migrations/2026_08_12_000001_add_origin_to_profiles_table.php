<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks how a profile came to exist. `registered` (default) is a person who
 * signed up or was created by an admin; `signature_only` is an auto-created
 * placeholder holding just a name + signature captured from a form submission,
 * so the SuperAdmin Profile Manager can distinguish and later claim them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('origin', 20)->default('registered')->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
