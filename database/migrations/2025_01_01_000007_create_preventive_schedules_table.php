<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('title');
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'yearly', 'usage_based']);
            $table->unsignedInteger('frequency_value')->default(1);
            $table->date('last_generated_date')->nullable();
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_schedules');
    }
};
