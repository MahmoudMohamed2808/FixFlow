<?php

use App\Models\Invoice;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Notifications\InventoryNotification;
use App\Notifications\InvoiceNotification;
use App\Notifications\ScheduleNotification;
use App\Notifications\WorkOrderNotification;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    Notification::fake();
});

it('sends demo database-only notifications from sibling seeder data', function () {
    $reporter = User::factory()->create();
    WorkOrder::factory()->create(['status' => 'open', 'reported_by' => $reporter->id]);
    WorkOrder::factory()->create(['status' => 'in_progress', 'reported_by' => $reporter->id]);
    WorkOrderComment::factory()->for(WorkOrder::factory()->create(), 'workOrder')->create();
    Invoice::factory()->create();
    SparePart::factory()->create(['quantity_in_stock' => 50, 'minimum_quantity' => 5]);
    PreventiveSchedule::factory()->create();

    (new NotificationSeeder)->run();

    Notification::assertSentToTimes(WorkOrderNotification::class, 3);
    Notification::assertSentTo($this->admin, InvoiceNotification::class);
    Notification::assertSentTo($this->admin, InventoryNotification::class);
    Notification::assertSentTo($this->admin, ScheduleNotification::class);

    // Demo seed content must stay database-only so `db:seed` never queues mail.
    Notification::assertSentTo(
        $this->admin,
        WorkOrderNotification::class,
        fn (WorkOrderNotification $notification, array $channels) => $channels === ['database']
    );
});

it('does not duplicate demo notifications on re-run', function () {
    WorkOrder::factory()->create(['status' => 'open']);
    Invoice::factory()->create();

    $this->admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => WorkOrderNotification::class,
        'data' => ['title' => 'seeded', 'body' => 'seeded', 'url' => '/', 'action' => 'work_order.created'],
    ]);

    (new NotificationSeeder)->run();

    Notification::assertNothingSent();
});

it('does nothing when no admin exists', function () {
    User::query()->delete();

    WorkOrder::factory()->create(['status' => 'open']);

    (new NotificationSeeder)->run();

    Notification::assertNothingSent();
});
