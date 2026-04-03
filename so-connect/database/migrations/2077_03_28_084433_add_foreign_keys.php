<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('profile')->references('profile_id')->on('profiles')->onDelete('set null');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('address')->references('profile_address_id')->on('profile_addresses')->onDelete('set null');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('approval')->references('approval_id')->on('approvals')->onDelete('set null');
        });

        Schema::table('organization_officers', function (Blueprint $table) {
            $table->foreign('member')->references('member_id')->on('members')->onDelete('set null');
            $table->foreign('yearterm')->references('year_term_code')->on('year_term_master')->onDelete('set null');
            $table->foreign('organization')->references('organization_id')->on('organizations')->onDelete('set null');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('detail')->references('organization_detail_id')->on('organization_details')->onDelete('set null');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('set null');
        });
        Schema::table('approvals', function (Blueprint $table) {
            $table->foreign('admin')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('request')->references('request_id')->on('requests')->onDelete('set null');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('creator')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('organization')->references('organization_id')->on('organizations')->onDelete('set null');
            $table->foreign('event_detail')->references('event_detail_id')->on('event_details')->onDelete('set null');
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('generated_for')->references('user_id')->on('users')->onDelete('set null');
        });
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('author')->references('org_officer_id')->on('organization_officers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Resolve actual constraint names from information_schema so rollback
        // works even if FK names changed between migration revisions.
        $this->dropForeignKeyIfExists('users', 'profile');
        $this->dropForeignKeyIfExists('profiles', 'address');
        $this->dropForeignKeyIfExists('members', 'user');
        $this->dropForeignKeyIfExists('members', 'approval');
        $this->dropForeignKeyIfExists('organization_officers', 'member');
        $this->dropForeignKeyIfExists('organization_officers', 'yearterm');
        $this->dropForeignKeyIfExists('organization_officers', 'organization');
        $this->dropForeignKeyIfExists('organizations', 'officer');
        $this->dropForeignKeyIfExists('organizations', 'detail');
        $this->dropForeignKeyIfExists('requests', 'user');
        $this->dropForeignKeyIfExists('requests', 'request');
        $this->dropForeignKeyIfExists('approvals', 'admin');
        $this->dropForeignKeyIfExists('events', 'creator');
        $this->dropForeignKeyIfExists('events', 'event_detail');
        $this->dropForeignKeyIfExists('events', 'organization');
        $this->dropForeignKeyIfExists('reports', 'generated_for');
        $this->dropForeignKeyIfExists('evaluations', 'author');
    }

    private function dropForeignKeyIfExists(string $tableName, string $columnName): void
    {
        $database = DB::getDatabaseName();

        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS constraint_name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1',
            [$database, $tableName, $columnName]
        );

        if (! $constraint || ! isset($constraint->constraint_name)) {
            return;
        }

        $safeTable = str_replace('`', '``', $tableName);
        $safeConstraint = str_replace('`', '``', $constraint->constraint_name);

        DB::statement("ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeConstraint}`");
    }
};
