<?php

use App\Models\Budget;
use App\Models\Client;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\FinancialReportService;
use Database\Seeders\DemoFinancialSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    User::factory()->create()->assignRole('Admin');
    Department::factory()->create(['name' => 'الصيانة']);
});

it('seeds a coherent financial demo dataset', function () {
    (new DemoFinancialSeeder)->run();

    expect(Client::count())->toBe(3);

    $paid = Invoice::where('status', 'paid')->first();
    expect($paid)->not->toBeNull();
    expect($paid->total_amount)->toBe('3500.00');
    expect($paid->work_order_id)->not->toBeNull();

    expect(Invoice::where('status', 'sent')->count())->toBe(1);
    expect(Budget::count())->toBe(2);

    $laborOrder = WorkOrder::where('description', 'إصلاح ضاغط الهواء الرئيسي')->first();
    expect((float) $laborOrder->cost)->toBe(1000.0);
});

it('is idempotent on repeated runs', function () {
    (new DemoFinancialSeeder)->run();
    (new DemoFinancialSeeder)->run();

    expect(Client::count())->toBe(3);
    expect(Invoice::count())->toBe(3);
    expect(Budget::count())->toBe(2);
});

it('feeds the financial summary with demo income and expenses', function () {
    (new DemoFinancialSeeder)->run();

    $year = date('Y');
    $summary = (new FinancialReportService)->summary(
        Carbon::parse("{$year}-01-01"),
        Carbon::parse("{$year}-12-31"),
    );

    // المصروف: 1000 أجور + 2500 مباشر + 300 أجور داخلي = 3800
    expect($summary['total_expenses'])->toBe(3800.0);
    expect($summary['total_income'])->toBe(3500.0);
    expect($summary['net_profit'])->toBe(-300.0);
});
