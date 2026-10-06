<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a request to the form page it was made from, so the Request Records
 * "Request Type" column can show the originating form page's name. Backfills
 * from the payload's form_id where the document-generation flow already
 * recorded it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('form_id')->nullable()->index()->after('request_type_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE requests
                SET form_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_id')) AS UNSIGNED)
                WHERE payload IS NOT NULL
                  AND JSON_EXTRACT(payload, '$.form_id') IS NOT NULL
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.form_id')) REGEXP '^[0-9]+$'
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['form_id']);
            $table->dropColumn('form_id');
        });
    }
};
