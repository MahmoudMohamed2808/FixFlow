<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\InventoryNotification;
use App\Notifications\InvoiceNotification;
use App\Notifications\ScheduleNotification;
use App\Notifications\WorkOrderNotification;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds a small curated set of demo (database-only) notifications.
 *
 * Domain seeders stay silent on purpose — same rule as ActivityLog::record(),
 * which is only called from Livewire actions to skip seeder/factory noise.
 * This seeder runs LAST and communicates with its siblings by reading the
 * demo data they created (open orders, comments, invoices, stock, schedules)
 * so the notification bell has representative content after `db:seed`.
 *
 * Idempotent: skips entirely once the admin already has notifications.
 * Never queues mail: only database-only actions are used here.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Role::where('name', 'Admin')->exists()) {
            return;
        }

        $admin = User::role('Admin')->where('is_active', true)->first();

        if ($admin === null) {
            return;
        }

        if ($admin->notifications()->exists()) {
            return;
        }

        $service = app(NotificationService::class);

        // Generic demo actor: anyone but the admin so the admin stays a recipient.
        $actor = User::query()
            ->whereKeyNot($admin->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $openOrder = WorkOrder::query()->where('status', 'open')->latest()->first();
        $progressOrder = WorkOrder::query()->where('status', 'in_progress')->latest()->first();
        $commentedOrder = WorkOrder::query()->whereHas('comments')->latest()->first();
        $invoice = Invoice::query()->latest()->first();
        $healthyPart = SparePart::query()
            ->whereColumn('quantity_in_stock', '>', 'minimum_quantity')
            ->orderBy('name')
            ->first();
        $schedule = PreventiveSchedule::query()->orderBy('next_due_date')->first();

        if ($openOrder !== null) {
            $service->workOrder($openOrder, WorkOrderNotification::CREATED, $openOrder->reportedBy ?? $admin);
        }

        if ($progressOrder !== null) {
            $service->workOrder(
                $progressOrder,
                WorkOrderNotification::STATUS_CHANGED,
                $progressOrder->assignedTo ?? $admin,
                ['status' => 'in_progress', 'status_label' => 'قيد التنفيذ']
            );
        }

        if ($commentedOrder !== null && $commentedOrder->comments()->exists()) {
            $comment = $commentedOrder->comments()->latest()->first();

            $service->workOrder(
                $commentedOrder,
                WorkOrderNotification::COMMENTED,
                $comment?->user ?? $admin,
                ['comment' => (string) $comment?->comment]
            );
        }

        if ($invoice !== null) {
            $service->invoice($invoice, InvoiceNotification::CREATED, $actor);
        }

        if ($healthyPart !== null) {
            $service->sparePart($healthyPart, InventoryNotification::STOCK_ADJUSTED, $actor, ['was_below' => false]);
        }

        if ($schedule !== null) {
            $service->schedule($schedule, ScheduleNotification::DUE_SOON, $actor);
        }
    }
}
