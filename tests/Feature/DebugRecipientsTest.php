<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderNotification;
use App\Services\NotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;

it('debug service send', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $reporter = User::factory()->create();

    $order = WorkOrder::factory()->create(['status' => 'open', 'reported_by' => $reporter->id]);

    app(NotificationService::class)->workOrder($order, WorkOrderNotification::CREATED, $reporter);

    dump([
        'queue_default' => config('queue.default'),
        'env_qc' => getenv('QUEUE_CONNECTION'),
        'admin_notifications' => $admin->notifications()->count(),
        'all_notifications' => DB::table('notifications')->count(),
        'jobs' => DB::table('jobs')->count(),
    ]);

    expect(true)->toBeTrue();
});
