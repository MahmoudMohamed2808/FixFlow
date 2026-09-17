<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            // client_id = null يعني شغل داخلي للشركة، لو متحدد يبقى شغل مدفوع لعميل خارجي
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->enum('type', ['preventive', 'corrective']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'in_progress', 'completed', 'cancelled'])
                ->default('open');
            $table->text('description')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('preventive_schedules')->nullOnDelete();
            $table->dateTime('scheduled_date')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            // cost = المصروف الداخلي الفعلي (قطع غيار + أجور)، بيتحسب تلقائي في الموديل
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
