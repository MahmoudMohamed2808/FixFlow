<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\AppNotification;
use App\Notifications\AssetNotification;
use App\Notifications\InventoryNotification;
use App\Notifications\InvoiceNotification;
use App\Notifications\ScheduleNotification;
use App\Notifications\SystemNotification;
use App\Notifications\WorkOrderNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(private NotificationRecipients $recipients) {}

    public function currentActor(?User $actor = null): ?User
    {
        if ($actor !== null) {
            return $actor;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $recipients
     */
    private function send(iterable $recipients, AppNotification $notification, ?User $actor = null): void
    {
        $targets = collect($recipients)
            ->filter(fn ($user) => $user instanceof User && (bool) $user->is_active)
            ->reject(fn (User $user) => $actor !== null && $user->is($actor))
            ->unique('id')
            ->values();

        if ($targets->isEmpty()) {
            return;
        }

        Notification::send($targets, $notification);
    }

    public function workOrder(WorkOrder $order, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $event === WorkOrderNotification::ASSIGNED && isset($meta['assigned_to'])
                ? $this->assigneePlusManagers((int) $meta['assigned_to'])
                : $this->recipients->forWorkOrder($order),
            new WorkOrderNotification($order->refresh(), $event, $actor, $meta),
            $actor
        );
    }

    public function workOrderDeleted(WorkOrder $order, ?User $actor = null): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new WorkOrderNotification($order, WorkOrderNotification::DELETED, $actor),
            $actor
        );
    }

    public function invoice(Invoice $invoice, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new InvoiceNotification($invoice->refresh(), $event, $actor, $meta),
            $actor
        );
    }

    public function invoiceDeleted(Invoice $invoice, ?User $actor = null): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new InvoiceNotification($invoice, InvoiceNotification::DELETED, $actor),
            $actor
        );
    }

    public function sparePart(SparePart $part, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new InventoryNotification($part->refresh(), $event, $actor, $meta),
            $actor
        );

        if ($event === InventoryNotification::STOCK_ADJUSTED) {
            $this->checkLowStock($part, $actor, $meta);
        }
    }

    public function sparePartDeleted(SparePart $part, ?User $actor = null): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new InventoryNotification($part, InventoryNotification::DELETED, $actor),
            $actor
        );
    }

    public function notifyLowStock(SparePart $part, ?User $actor = null): void
    {
        $this->send(
            $this->recipients->managers(),
            new InventoryNotification($part->refresh(), InventoryNotification::LOW_STOCK, $this->currentActor($actor)),
            $this->currentActor($actor)
        );
    }

    public function notifyBackInStock(SparePart $part, ?User $actor = null): void
    {
        $this->send(
            $this->recipients->managers(),
            new InventoryNotification($part->refresh(), InventoryNotification::BACK_IN_STOCK, $this->currentActor($actor)),
            $this->currentActor($actor)
        );
    }

    private function checkLowStock(SparePart $part, ?User $actor, array $meta): void
    {
        $fresh = $part->refresh();

        if ($fresh->isBelowMinimum()) {
            $this->notifyLowStock($fresh, $actor);

            return;
        }

        if (($meta['was_below'] ?? false) && ! $fresh->isBelowMinimum()) {
            $this->notifyBackInStock($fresh, $actor);
        }
    }

    public function asset(Asset $asset, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new AssetNotification($asset->refresh(), $event, $actor, $meta),
            $actor
        );
    }

    public function assetDeleted(Asset $asset, ?User $actor = null): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new AssetNotification($asset, AssetNotification::DELETED, $actor),
            $actor
        );
    }

    public function schedule(PreventiveSchedule $schedule, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new ScheduleNotification($schedule->refresh(), $event, $actor, $meta),
            $actor
        );
    }

    public function scheduleDeleted(PreventiveSchedule $schedule, ?User $actor = null): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new ScheduleNotification($schedule, ScheduleNotification::DELETED, $actor),
            $actor
        );
    }

    public function system(Model $subject, string $domain, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new SystemNotification($subject, $event, $domain, $actor, $meta),
            $actor
        );
    }

    public function client(Client $client, string $event, ?User $actor = null): void
    {
        $this->system($client, 'client', $event, $actor);
    }

    public function supplier(Supplier $supplier, string $event, ?User $actor = null): void
    {
        $this->system($supplier, 'supplier', $event, $actor);
    }

    public function budget(Budget $budget, string $event, ?User $actor = null, array $meta = []): void
    {
        $this->system($budget, 'budget', $event, $actor, $meta);
    }

    public function userLifecycle(User $user, string $event, ?User $actor = null, array $meta = []): void
    {
        $actor = $this->currentActor($actor);

        $this->send(
            $this->recipients->managers(),
            new SystemNotification($user, $event, 'user', $actor, $meta),
            $actor
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function assigneePlusManagers(int $assigneeId): Collection
    {
        $users = $this->recipients->managers();

        $assignee = User::query()->whereKey($assigneeId)->where('is_active', true)->first();

        if ($assignee !== null && ! $users->contains($assignee)) {
            $users->push($assignee);
        }

        return $users;
    }
}
