<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accreditation lifecycle state for organizations.
 *
 *  - accreditation_status: 'active' (compliant / not yet enforced) or
 *    'disabled' (missed the deadline — posts hidden, members blocked, org
 *    hidden). A hard purge removes the row entirely, so there is no 'purged'.
 *  - accreditation_disabled_at: when the org was auto-disabled; the purge
 *    grace period is counted from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('accreditation_status', 32)->default('active')->after('detail');
            $table->timestamp('accreditation_disabled_at')->nullable()->after('accreditation_status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['accreditation_status', 'accreditation_disabled_at']);
        });
    }
};
