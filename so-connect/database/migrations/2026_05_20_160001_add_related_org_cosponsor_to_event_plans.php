<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->boolean('related_to_organization')->default(false)->after('extension_services');
            $table->unsignedSmallInteger('cosponsor_count')->nullable()->after('sponsor_other');
        });
    }

    public function down(): void
    {
        Schema::table('event_plans', function (Blueprint $table) {
            $table->dropColumn(['related_to_organization', 'cosponsor_count']);
        });
    }
};
