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
            $table->string('name')->default('Untitled Form')->after('id');
            $table->text('description_text')->nullable()->after('name');
            $table->unsignedBigInteger('organization_id')->nullable()->after('description_text');
            $table->unsignedBigInteger('created_by')->nullable()->after('organization_id');
            $table->boolean('is_active')->default(true)->after('created_by');
            $table->boolean('is_published')->default(false)->after('is_active');

            $table->index('organization_id', 'forms_organization_id_idx');
            $table->index('created_by', 'forms_created_by_idx');

            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('form_id')->nullable()->after('id');
            $table->string('field_key')->nullable()->after('form_id');
            $table->string('field_label')->nullable()->after('field_key');
            $table->string('field_type')->default('text')->after('field_label');
            $table->boolean('is_required')->default(false)->after('field_type');
            $table->unsignedInteger('field_order')->default(0)->after('is_required');
            $table->string('placeholder_hint')->nullable()->after('field_order');
            $table->json('field_options')->nullable()->after('placeholder_hint');

            $table->index('form_id', 'form_descriptions_form_id_idx');
            $table->unique(['form_id', 'field_key'], 'form_descriptions_form_field_unique');

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnDelete();
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedBigInteger('form_id')->nullable()->after('id');
            $table->unsignedBigInteger('organization_id')->nullable()->after('form_id');
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('organization_id');
            $table->string('template_name')->default('Untitled Template')->after('uploaded_by');
            $table->string('docx_path')->nullable()->after('template_name');
            $table->unsignedInteger('version')->default(1)->after('docx_path');
            $table->boolean('is_active')->default(true)->after('version');

            $table->index('form_id', 'templates_form_id_idx');
            $table->index('organization_id', 'templates_organization_id_idx');
            $table->index('uploaded_by', 'templates_uploaded_by_idx');

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->nullOnDelete();

            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('organizations')
                ->nullOnDelete();

            $table->foreign('uploaded_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('template_descriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('id');
            $table->unsignedBigInteger('form_description_id')->nullable()->after('template_id');
            $table->string('placeholder_key')->nullable()->after('form_description_id');
            $table->string('field_key')->nullable()->after('placeholder_key');
            $table->boolean('is_required')->default(true)->after('field_key');

            $table->index('template_id', 'template_descriptions_template_id_idx');
            $table->index('form_description_id', 'template_descriptions_form_description_id_idx');
            $table->unique(['template_id', 'placeholder_key'], 'template_descriptions_template_placeholder_unique');

            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->cascadeOnDelete();

            $table->foreign('form_description_id')
                ->references('id')
                ->on('form_descriptions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_descriptions', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['form_description_id']);
            $table->dropUnique('template_descriptions_template_placeholder_unique');
            $table->dropIndex('template_descriptions_template_id_idx');
            $table->dropIndex('template_descriptions_form_description_id_idx');
            $table->dropColumn([
                'template_id',
                'form_description_id',
                'placeholder_key',
                'field_key',
                'is_required',
            ]);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['uploaded_by']);
            $table->dropIndex('templates_form_id_idx');
            $table->dropIndex('templates_organization_id_idx');
            $table->dropIndex('templates_uploaded_by_idx');
            $table->dropColumn([
                'form_id',
                'organization_id',
                'uploaded_by',
                'template_name',
                'docx_path',
                'version',
                'is_active',
            ]);
        });

        Schema::table('form_descriptions', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropUnique('form_descriptions_form_field_unique');
            $table->dropIndex('form_descriptions_form_id_idx');
            $table->dropColumn([
                'form_id',
                'field_key',
                'field_label',
                'field_type',
                'is_required',
                'field_order',
                'placeholder_hint',
                'field_options',
            ]);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex('forms_organization_id_idx');
            $table->dropIndex('forms_created_by_idx');
            $table->dropColumn([
                'name',
                'description_text',
                'organization_id',
                'created_by',
                'is_active',
                'is_published',
            ]);
        });
    }
};
