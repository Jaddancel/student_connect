<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the columns that were on members into organization_officers
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->unsignedBigInteger('user')->nullable()->after('organization');
            $table->unsignedBigInteger('approval')->nullable()->after('user');
            $table->dateTime('member_since')->nullable()->after('approval');
        });

        // 2. Copy user, approval, member_since from members into each officer row
        DB::statement('
            UPDATE organization_officers oo
            JOIN members m ON m.member_id = oo.member
            SET oo.user         = m.user,
                oo.approval     = m.approval,
                oo.member_since = m.member_since
        ');

        // 3. Drop the FK on organization_officers.member
        $this->dropForeignKeyIfExists('organization_officers', 'member');

        // 4. Drop the member column from organization_officers
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->dropColumn('member');
        });

        // 5. Drop FKs from members, then drop the table
        $this->dropForeignKeyIfExists('members', 'user');
        $this->dropForeignKeyIfExists('members', 'approval');
        Schema::dropIfExists('members');

        // 6. Add new FKs to organization_officers
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('approval')->references('approval_id')->on('approvals')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop new FKs
        $this->dropForeignKeyIfExists('organization_officers', 'user');
        $this->dropForeignKeyIfExists('organization_officers', 'approval');

        // Re-create members table (schema only — data is not recoverable)
        Schema::create('members', function (Blueprint $table) {
            $table->bigIncrements('member_id');
            $table->unsignedBigInteger('organization')->nullable();
            $table->unsignedBigInteger('approval')->nullable();
            $table->unsignedBigInteger('user')->nullable();
            $table->dateTime('member_since')->useCurrent();
        });

        // Re-add member column to organization_officers
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->unsignedBigInteger('member')->nullable()->after('organization');
        });

        // Drop the columns added in up()
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->dropColumn(['user', 'approval', 'member_since']);
        });

        // Restore FKs on members
        Schema::table('members', function (Blueprint $table) {
            $table->foreign('user')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('approval')->references('approval_id')->on('approvals')->onDelete('set null');
        });

        // Restore FK on organization_officers.member
        Schema::table('organization_officers', function (Blueprint $table) {
            $table->foreign('member')->references('member_id')->on('members')->onDelete('set null');
        });
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
