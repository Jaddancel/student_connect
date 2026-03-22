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
            try {
                $table->foreign('profile_id')->references('profile_id')->on('profiles')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to organizations for organization_detail
        Schema::table('organizations', function (Blueprint $table) {
            try {
                $table->foreign('organization_detail')->references('organization_detail_id')->on('organization_details')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign keys to templates
        Schema::table('templates', function (Blueprint $table) {
            try {
                $table->foreign('template_author')->references('member_id')->on('members')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
            try {
                $table->foreign('template_description')->references('template_desc_id')->on('template_descriptions')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign keys to documents
        Schema::table('documents', function (Blueprint $table) {
            try {
                $table->foreign('document_desc_id')->references('document_desc_id')->on('document_descriptions')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
            try {
                $table->foreign('document_author')->references('member_id')->on('members')->onDelete('set null');
            } catch (Exception $e) {
                // Foreign key might already exist
            }
        });

        // Add foreign key to evaluations for evaluation_author
        Schema::table('evaluations', function (Blueprint $table) {
            try {
                $table->foreign('evaluation_author')->references('member_id')->on('members')->onDelete('set null');
            } catch (Exception $e) {
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
            $table->dropForeign(['profile_id']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['organization_detail']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['template_author']);
            $table->dropForeign(['template_description']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_desc_id']);
            $table->dropForeign(['document_author']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['evaluation_author']);
        });
    }
};
