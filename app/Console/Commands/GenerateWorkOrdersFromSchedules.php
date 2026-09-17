<?php

namespace App\Console\Commands;

use App\Models\PreventiveSchedule;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class GenerateWorkOrdersFromSchedules extends Command
{
    protected $signature = 'cmms:generate-work-orders';

    protected $description = 'Generate preventive work orders for schedules that are due.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Date::today();
        $generated = 0;

        PreventiveSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', $today)
            ->with('asset')
            ->orderBy('next_due_date')
            ->each(function (PreventiveSchedule $schedule) use ($today, &$generated) {
                $order = WorkOrder::create([
                    'asset_id' => $schedule->asset_id,
                    'schedule_id' => $schedule->id,
                    'type' => 'preventive',
                    'priority' => 'medium',
                    'status' => 'open',
                    'description' => __('صيانة وقائية: ').$schedule->title,
                    'scheduled_date' => $schedule->next_due_date->startOfDay(),
                ]);

                $generated++;
                $this->info("Generated work order [{$order->code}] for schedule #{$schedule->id}.");

                $schedule->update([
                    'last_generated_date' => $today,
                    'next_due_date' => $this->nextDueDate($schedule),
                ]);
            });

        $this->info("Generated {$generated} preventive work order(s).");

        return self::SUCCESS;
    }

    protected function nextDueDate(PreventiveSchedule $schedule): CarbonImmutable
    {
        $from = now();

        return match ($schedule->frequency_type) {
            'daily' => $from->addDays($schedule->frequency_value),
            'weekly' => $from->addWeeks($schedule->frequency_value),
            'yearly' => $from->addYears($schedule->frequency_value),
            default => $from->addMonths($schedule->frequency_value),
        };
    }
}
