<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedBigInteger('request_type_id')->nullable()->after('description_text');
            $table->index('request_type_id', 'forms_request_type_id_idx');

            $table->foreign('request_type_id')
                ->references('request_type_id')
                ->on('request_types')
                ->nullOnDelete();
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('request_type_id')->nullable()->after('action_type');
            $table->unsignedBigInteger('organization_id')->nullable()->after('request_type_id');
            $table->unsignedBigInteger('requested_by')->nullable()->after('organization_id');
            $table->json('payload')->nullable()->after('requested_by');

            $table->index('request_type_id', 'requests_request_type_id_idx');
            $table->index('organization_id', 'requests_organization_id_idx');
            $table->index('requested_by', 'requests_requested_by_idx');

            $table->foreign('request_type_id')
                ->references('request_type_id')
                ->on('request_types')
                ->nullOnDelete();

            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->nullOnDelete();

            $table->foreign('requested_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['request_type_id']);
            $table->dropIndex('requests_requested_by_idx');
            $table->dropIndex('requests_organization_id_idx');
            $table->dropIndex('requests_request_type_id_idx');
            $table->dropColumn([
                'request_type_id',
                'organization_id',
                'requested_by',
                'payload',
            ]);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['request_type_id']);
            $table->dropIndex('forms_request_type_id_idx');
            $table->dropColumn('request_type_id');
        });
    }
};
