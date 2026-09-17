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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index('assigned_to');
            $table->index('scheduled_date');
            $table->index('completed_at');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('location_id');
            $table->index('category_id');
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->index('supplier_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['scheduled_date']);
            $table->dropIndex(['completed_at']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
            $table->dropIndex(['category_id']);
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
