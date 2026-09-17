<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLabor;
use App\Services\FinancialReportService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('generates sequential invoice numbers automatically', function () {
    $first = Invoice::factory()->create();
    $second = Invoice::factory()->create();

    expect($first->invoice_number)->toBe('INV-00001');
    expect($second->invoice_number)->toBe('INV-00002');
});

it('calculates invoice item totals and rolls them up to the invoice', function () {
    $invoice = Invoice::factory()->create(['tax' => 100]);

    InvoiceItem::factory()->for($invoice, 'invoice')->create(['quantity' => 2, 'unit_price' => 1500]);
    InvoiceItem::factory()->for($invoice, 'invoice')->create(['quantity' => 1, 'unit_price' => 500]);

    expect($invoice->fresh()->subtotal)->toBe('3500.00');
    expect($invoice->fresh()->total_amount)->toBe('3600.00');
});

it('recalculates invoice totals when an item is deleted', function () {
    $invoice = Invoice::factory()->create();
    $item = InvoiceItem::factory()->for($invoice, 'invoice')->create(['quantity' => 1, 'unit_price' => 1000]);

    expect($invoice->fresh()->total_amount)->toBe('1000.00');

    $item->delete();

    expect($invoice->fresh()->total_amount)->toBe('0.00');
});

it('marks an invoice as paid with a timestamp', function () {
    $invoice = Invoice::factory()->create(['status' => 'sent']);

    $invoice->markAsPaid();

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->paid_at)->not->toBeNull();
});

it('tracks total paid and outstanding amounts per client', function () {
    $client = Client::factory()->create();

    Invoice::factory()->for($client, 'client')->paid()->create(['total_amount' => 2000, 'subtotal' => 2000]);
    Invoice::factory()->for($client, 'client')->create(['status' => 'sent', 'total_amount' => 1500, 'subtotal' => 1500]);
    Invoice::factory()->for($client, 'client')->create(['status' => 'overdue', 'total_amount' => 500, 'subtotal' => 500]);
    Invoice::factory()->for($client, 'client')->create(['status' => 'draft', 'total_amount' => 999, 'subtotal' => 999]);

    expect($client->total_paid)->toBe(2000.0);
    expect($client->total_outstanding)->toBe(2000.0);
});

it('calculates labor cost automatically and rolls it into the work order cost', function () {
    $order = WorkOrder::factory()->create(['cost' => 0]);

    WorkOrderLabor::factory()->for($order, 'workOrder')->create(['hours_spent' => 2, 'hourly_rate' => 100]);

    $order = $order->fresh();

    expect((float) $order->laborEntries->first()->cost)->toBe(200.0);
    expect((float) $order->cost)->toBe(200.0);
});

it('combines parts and labor into the work order cost', function () {
    $order = WorkOrder::factory()->create(['cost' => 0]);
    $part = SparePart::factory()->create(['quantity_in_stock' => 100, 'unit_price' => 50]);

    $order->parts()->create(['spare_part_id' => $part->id, 'quantity_used' => 2, 'unit_cost' => 50]);
    WorkOrderLabor::factory()->for($order, 'workOrder')->create(['hours_spent' => 1, 'hourly_rate' => 100]);

    $order = $order->fresh();

    expect($order->parts_cost)->toBe(100.0);
    expect($order->labor_cost)->toBe(100.0);
    expect((float) $order->cost)->toBe(200.0);
});

it('links a work order to a client or leaves it internal', function () {
    $internal = WorkOrder::factory()->create(['client_id' => null]);
    $client = Client::factory()->create();
    $external = WorkOrder::factory()->create(['client_id' => $client->id]);

    expect($internal->client)->toBeNull();
    expect($external->client->is($client))->toBeTrue();
    expect($client->workOrders)->toHaveCount(1);
});

it('computes budget spending, remaining, usage and over-budget state', function () {
    $department = Department::factory()->create();
    $location = Location::factory()->create(['department_id' => $department->id]);
    $asset = Asset::factory()->create(['location_id' => $location->id]);

    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => now()->subDays(5),
        'cost' => 3000,
    ]);
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => now()->subDays(40),
        'cost' => 7000,
    ]);

    $budget = Budget::factory()->forDepartment($department)->create([
        'period_start' => now()->subDays(10)->toDateString(),
        'period_end' => now()->addDays(10)->toDateString(),
        'allocated_amount' => 5000,
    ]);

    expect($budget->spent_amount)->toBe(3000.0);
    expect($budget->remaining_amount)->toBe(2000.0);
    expect($budget->usage_percent)->toBe(60.0);
    expect($budget->isOverBudget())->toBeFalse();

    $budget->update(['allocated_amount' => 2000]);

    expect($budget->fresh()->isOverBudget())->toBeTrue();
});

it('rejects a sub-category under a parent from another department', function () {
    $first = Department::factory()->create();
    $second = Department::factory()->create();
    $parent = AssetCategory::factory()->create(['department_id' => $first->id]);

    expect(fn () => AssetCategory::factory()->create([
        'department_id' => $second->id,
        'parent_id' => $parent->id,
    ]))->toThrow(ValidationException::class);
});

it('scopes categories per department', function () {
    $first = Department::factory()->create();
    $second = Department::factory()->create();
    AssetCategory::factory()->count(2)->create(['department_id' => $first->id]);
    AssetCategory::factory()->create(['department_id' => $second->id]);

    expect(AssetCategory::forDepartment($first->id)->count())->toBe(2);
    expect($first->assetCategories)->toHaveCount(2);
});

it('summarizes expenses, income, net profit and margin', function () {
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => Carbon::parse('2026-03-10 10:00'),
        'cost' => 4000,
    ]);

    $client = Client::factory()->create();
    Invoice::factory()->for($client, 'client')->paid()->create([
        'total_amount' => 10000,
        'subtotal' => 10000,
        'paid_at' => Carbon::parse('2026-04-01 10:00'),
    ]);
    // غير مدفوعة: لا تُحتسب ضمن المكتسب
    Invoice::factory()->for($client, 'client')->create([
        'status' => 'sent',
        'total_amount' => 5000,
        'subtotal' => 5000,
    ]);

    $summary = (new FinancialReportService)->summary(
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-12-31'),
    );

    expect($summary['total_expenses'])->toBe(4000.0);
    expect($summary['total_income'])->toBe(10000.0);
    expect($summary['net_profit'])->toBe(6000.0);
    expect($summary['profit_margin_percent'])->toBe(60.0);
});

it('ranks most expensive assets and top paying clients', function () {
    $cheap = Asset::factory()->create();
    $pricey = Asset::factory()->create();
    WorkOrder::factory()->for($cheap)->create(['scheduled_date' => Carbon::parse('2026-05-01 10:00'), 'cost' => 100]);
    WorkOrder::factory()->for($pricey)->create(['scheduled_date' => Carbon::parse('2026-05-02 10:00'), 'cost' => 900]);

    $small = Client::factory()->create();
    $big = Client::factory()->create();
    Invoice::factory()->for($small, 'client')->paid()->create(['total_amount' => 500, 'subtotal' => 500, 'paid_at' => Carbon::parse('2026-05-03 10:00')]);
    Invoice::factory()->for($big, 'client')->paid()->create(['total_amount' => 4500, 'subtotal' => 4500, 'paid_at' => Carbon::parse('2026-05-04 10:00')]);

    $service = new FinancialReportService;
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-12-31');

    $topAssets = $service->expensesByAsset($from, $to);
    expect($topAssets->first()->asset_id)->toBe($pricey->id);
    expect((float) $topAssets->first()->total_cost)->toBe(900.0);

    $topClients = $service->incomeByClient($from, $to);
    expect($topClients->first()->client_id)->toBe($big->id);
    expect((float) $topClients->first()->total_income)->toBe(4500.0);
});

it('stores an hourly rate on the user for labor costing', function () {
    $tech = User::factory()->create(['hourly_rate' => 120.50]);

    expect($tech->fresh()->hourly_rate)->toBe('120.50');

    $order = WorkOrder::factory()->create(['cost' => 0]);
    WorkOrderLabor::factory()->for($order, 'workOrder')->create([
        'user_id' => $tech->id,
        'hours_spent' => 2,
        'hourly_rate' => $tech->hourly_rate,
    ]);

    expect((float) $order->fresh()->cost)->toBe(241.0);
    expect($tech->laborEntries)->toHaveCount(1);
});

it('scopes the financial summary to a single department', function () {
    $year = date('Y');
    $first = Department::factory()->create();
    $second = Department::factory()->create();

    $assetOne = Asset::factory()->create([
        'location_id' => Location::factory()->create(['department_id' => $first->id]),
    ]);
    $assetTwo = Asset::factory()->create([
        'location_id' => Location::factory()->create(['department_id' => $second->id]),
    ]);

    WorkOrder::factory()->for($assetOne)->create([
        'scheduled_date' => "{$year}-06-10 10:00",
        'cost' => 1000,
    ]);
    $otherOrder = WorkOrder::factory()->for($assetTwo)->create([
        'scheduled_date' => "{$year}-06-11 10:00",
        'cost' => 5000,
    ]);

    // فاتورة مدفوعة مرتبطة بأمر من القسم الأول (تُنشئ عميلًا تلقائيًا).
    Invoice::factory()->forWorkOrder($otherOrder)->paid()->create([
        'total_amount' => 9000,
        'subtotal' => 9000,
        'paid_at' => "{$year}-07-01 10:00",
    ]);

    $service = new FinancialReportService;
    $from = Carbon::parse("{$year}-01-01");
    $to = Carbon::parse("{$year}-12-31");

    $scoped = $service->summary($from, $to, $first->id);

    expect($scoped['total_expenses'])->toBe(1000.0);
    expect($scoped['total_income'])->toBe(0.0);

    $scopedSecond = $service->summary($from, $to, $second->id);

    expect($scopedSecond['total_expenses'])->toBe(5000.0);
    expect($scopedSecond['total_income'])->toBe(9000.0);
});
