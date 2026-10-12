<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backing tables for the System Dashboard (user type 1):
 *  - system_request_metrics: hourly HTTP request / server-error (5xx) counters
 *    written by the RecordRequestMetrics middleware (error-rate widget).
 *  - document_downloads: one row per successful document download/generation
 *    response, written by the TrackDocumentDownload middleware (activity chart).
 *
 * Also retires the Profile Match request workflow: users left waiting on a
 * request that can no longer be reviewed are released so their next visit
 * sends them back to Create Profile, which now creates the profile directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_request_metrics', function (Blueprint $table) {
            $table->id();
            $table->dateTime('bucket_at')->unique();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('server_errors')->default(0);
        });

        Schema::create('document_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('kind', 32);
            $table->string('route_name')->nullable();
            $table->dateTime('downloaded_at')->index();
        });

        DB::table('users')
            ->where('profile_pending', true)
            ->where(fn ($query) => $query->whereNull('profile')->orWhere('profile', 0))
            ->update(['profile_pending' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
        Schema::dropIfExists('system_request_metrics');
    }
};
