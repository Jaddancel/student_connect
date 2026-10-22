<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accomplishment_media', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('organization_id')->index();
            $table->string('field_key')->nullable()->after('file_path');
            $table->string('original_name')->nullable()->after('activity_title');
            $table->string('mime_type', 120)->nullable()->after('original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->json('tags')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('accomplishment_media', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropColumn(['event_id', 'field_key', 'original_name', 'mime_type', 'file_size', 'tags']);
        });
    }
};
