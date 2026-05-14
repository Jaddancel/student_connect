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
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id('dashboard_widget_id');
            $table->string('role', 32);
            $table->string('widget_type', 64);
            $table->string('title');
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('column_span')->default(12);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('role', 'dashboard_widgets_role_idx');
            $table->index('widget_type', 'dashboard_widgets_type_idx');
            $table->index('sort_order', 'dashboard_widgets_sort_order_idx');
            $table->index('created_by', 'dashboard_widgets_created_by_idx');

            $table->foreign('created_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
