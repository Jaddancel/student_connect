<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('allows_guest_scan')->default(false)->after('is_published');
            $table->string('ocr_reference_docx', 500)->nullable()->after('allows_guest_scan');
            // Links this form to a hard-coded sign-up route (e.g. 'student-leader-directory').
            $table->string('directory_assignment_key', 100)->nullable()->after('ocr_reference_docx');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['allows_guest_scan', 'ocr_reference_docx', 'directory_assignment_key']);
        });
    }
};
