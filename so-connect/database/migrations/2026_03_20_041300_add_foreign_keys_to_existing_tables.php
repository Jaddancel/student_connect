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
        // Add foreign key to users table for profile_id
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_id')) {
                $table->unsignedBigInteger('profile_id')->nullable()->change();
            }
            // Only add foreign key if it doesn't exist
            try {
                $table->foreign('profile_id')->references('profile_id')->on('profiles')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to organizations for organization_detail
        Schema::table('organizations', function (Blueprint $table) {
            try {
                $table->foreign('organization_detail')->references('organization_detail_id')->on('organization_details')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to templates for template_author and template_description
        Schema::table('templates', function (Blueprint $table) {
            try {
                $table->foreign('template_author')->references('member_id')->on('members')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
            try {
                $table->foreign('template_description')->references('template_desc_id')->on('template_descriptions')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to members for approval_id
        Schema::table('members', function (Blueprint $table) {
            try {
                $table->foreign('approval_id')->references('approval_id')->on('approvals')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to events for event_detail
        Schema::table('events', function (Blueprint $table) {
            try {
                $table->foreign('event_detail')->references('event_detail_id')->on('event_details')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIfExists('users_profile_id_foreign');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeignIfExists('organizations_organization_detail_foreign');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeignIfExists('templates_template_author_foreign');
            $table->dropForeignIfExists('templates_template_description_foreign');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeignIfExists('members_approval_id_foreign');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeignIfExists('events_event_detail_foreign');
        });
    }
};
