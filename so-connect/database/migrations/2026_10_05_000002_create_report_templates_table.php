<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-authored report templates: a token definition (data) plus one or more
 * printed .docx slots, stored as `templates` rows keyed by report_template_id
 * so the OnlyOffice editor/revision plumbing is shared with the form builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->json('definition');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedBigInteger('report_template_id')->nullable()->after('form_id');
            $table->index(['report_template_id', 'is_active', 'slot_order'], 'templates_report_slots_idx');
            $table->foreign('report_template_id')->references('id')->on('report_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['report_template_id']);
            $table->dropIndex('templates_report_slots_idx');
            $table->dropColumn('report_template_id');
        });

        Schema::dropIfExists('report_templates');
    }
};
