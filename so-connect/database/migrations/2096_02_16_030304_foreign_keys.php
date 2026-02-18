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
