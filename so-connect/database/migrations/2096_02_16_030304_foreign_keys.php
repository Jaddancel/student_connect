<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

// Wise Words... 
// A model BelongsTo a parent when the parent_id is in the model.
// A model hasMany children when the model_id is in the children
// A model hasOne child when the model_id is in a child and you only want one of them.
//                                                          - some guy on Reddit

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('profile_id')->references('profile_id')->on('profiles')->onDelete('cascade');
            $table->foreign('user_type_code')->references('user_type_code')->on('user_type_master')->onDelete('cascade');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });

        Schema::table('administrators', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('cascade');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('role')->references('role_code')->on('role_master')->onDelete('cascade');
        });

        Schema::table('member_details', function (Blueprint $table) {
            $table->foreign('member_id')->references('member_id')->on('members')->onDelete('cascade');
        });

        Schema::table('member_organizations', function (Blueprint $table) {
            $table->foreign('member_detail_id')->references('member_detail_id')->on('member_details')->onDelete('cascade');
            $table->foreign('organization_id')->references('organization_id')->on('organizations')->onDelete('cascade');
        });

        Schema::table('presidents', function (Blueprint $table) {
            $table->foreign('member')->references('member_id')->on('members')->onDelete('cascade');
        });

        Schema::table('super_administrators', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('cascade');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('organization_type')->references('organization_type_code')->on('organization_type_master')->onDelete('cascade');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('occupation_code')->references('occupation_code')->on('occupation_master')->onDelete('cascade');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('evaluation_author')->references('president_id')->on('presidents')->onDelete('cascade');
            $table->foreign('evaluation_item')->references('evaluation_item_id')->on('evaluation_items')->onDelete('cascade');
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->foreign('evaluation_grade')->references('evaluation_grade_code')->on('evaluation_grade_enum')->onDelete('cascade');
        });

        Schema::table('evaluation_descriptions', function (Blueprint $table) {
            $table->foreign('grade')->references('evaluation_grade_code')->on('evaluation_grade_enum')->onDelete('cascade');
            $table->foreign('evaluation_item')->references('evaluation_item_id')->on('evaluation_items')->onDelete('cascade');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('approval_id')->references('approval_id')->on('approvals')->onDelete('cascade');
            $table->foreign('event_detail')->references('event_detail_id')->on('event_details')->onDelete('cascade');
        });

        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->foreign('form_organizations')->references('organization_id')->on('organizations')->onDelete('cascade');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreign('form_template')->references('template_id')->on('templates')->onDelete('cascade');
            $table->foreign('form_description')->references('form_description_id')->on('form_descriptions')->onDelete('cascade');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreign('template_description')->references('template_description_id')->on('template_descriptions')->onDelete('cascade');
        });

        Schema::table('approvals', function (Blueprint $table) {
            $table->foreign('approver_admin')->references('admin_id')->on('administrators')->onDelete('cascade');
        });

        Schema::table('document_descriptions', function (Blueprint $table) {
            $table->foreign('document_author')->references('member_id')->on('members')->onDelete('cascade');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('document_description')->references('document_description_id')->on('document_descriptions')->onDelete('cascade');
            $table->foreign('approval_id')->references('approval_id')->on('approvals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['organization_type']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['occupation_code']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['template_description']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['form_template']);
            $table->dropForeign(['form_description']);
        });

        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->dropForeign(['form_organizations']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
            $table->dropForeign(['event_detail']);
        });

        Schema::table('evaluation_descriptions', function (Blueprint $table) {
            $table->dropForeign(['grade']);
            $table->dropForeign(['evaluation_item']);
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->dropForeign(['evaluation_grade']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['evaluation_author']);
            $table->dropForeign(['evaluation_item']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_description']);
            $table->dropForeign(['approval_id']);
        });

        Schema::table('document_descriptions', function (Blueprint $table) {
            $table->dropForeign(['document_author']);
        });

        Schema::table('approvals', function (Blueprint $table) {
            $table->dropForeign(['approver_admin']);
        });

        Schema::table('super_administrators', function (Blueprint $table) {
            $table->dropForeign(['user']);
        });

        Schema::table('presidents', function (Blueprint $table) {
            $table->dropForeign(['member']);
        });

        Schema::table('member_organizations', function (Blueprint $table) {
            $table->dropForeign(['member_detail_id']);
            $table->dropForeign(['organization_id']);
        });

        Schema::table('member_details', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['user']);
            $table->dropForeign(['role']);
        });

        Schema::table('administrators', function (Blueprint $table) {
            $table->dropForeign(['user']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            $table->dropForeign(['user_type_code']);
        });
    }
};
