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
        Schema::table( 'users', function (Blueprint $table) {
            $table->foreign('user_type_code')
                ->references('user_type_code')
                ->on('user_type_master');
        });
        Schema::table( 'organizations', function (Blueprint $table) {
            $table->foreign('president_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });
        Schema::table( 'organizations', function (Blueprint $table) {
            $table->foreign('organization_type_code')
                ->references('organization_type_code')
                ->on('organization_type_master')
                ->onDelete('cascade');
        });
        Schema::table( 'members', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });
        Schema::table( 'members', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->onDelete('cascade');
        });
         Schema::table( 'members', function (Blueprint $table) {
            $table->foreign('role_code')
                ->references('role_code')
                ->on('role_master')
                ->onDelete('cascade');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('profile_id')
                ->references('profile_id')
                ->on('profiles')
                ->onDelete('set null');
        });
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('occupation_code')
                ->references('occupation_code')
                ->on('occupation_master')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
