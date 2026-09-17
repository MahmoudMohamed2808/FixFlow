<?php

use App\Models\Asset;
use App\Models\Budget;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

it('lets an admin create a budget', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $department = Department::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::budgets.index')
        ->call('openCreate')
        ->set('title', 'ميزانية 2026')
        ->set('departmentId', $department->id)
        ->set('periodStart', now()->subMonth()->toDateString())
        ->set('periodEnd', now()->addMonths(2)->toDateString())
        ->set('allocatedAmount', 50000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Budget::where('title', 'ميزانية 2026')->exists())->toBeTrue();
});

it('validates the budget period and amount', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::budgets.index')
        ->call('openCreate')
        ->set('title', '')
        ->set('periodStart', now()->toDateString())
        ->set('periodEnd', now()->subDay()->toDateString())
        ->set('allocatedAmount', -5)
        ->call('save')
        ->assertHasErrors(['title', 'periodEnd', 'allocatedAmount']);
});

it('lets an admin update and delete a budget', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $budget = Budget::factory()->create(['title' => 'قديمة']);

    Livewire::actingAs($admin)
        ->test('pages::budgets.index')
        ->call('openEdit', $budget->id)
        ->set('title', 'محدثة')
        ->call('save')
        ->assertHasNoErrors();

    expect($budget->fresh()->title)->toBe('محدثة');

    Livewire::actingAs($admin)
        ->test('pages::budgets.index')
        ->call('askDelete', $budget->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Budget::find($budget->id))->toBeNull();
});

it('displays spent amounts and over-budget state', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => now()->subDays(2),
        'cost' => 8000,
    ]);
    $budget = Budget::factory()->create([
        'title' => 'ميزانية ضيقة',
        'period_start' => now()->subDays(10)->toDateString(),
        'period_end' => now()->addDays(10)->toDateString(),
        'allocated_amount' => 5000,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::budgets.index')
        ->assertSee('ميزانية ضيقة')
        ->assertSee('8,000.00')
        ->assertSee('تجاوز');
});

it('forbids budget mutations without permission', function () {
    $viewer = User::factory()->create()->assignRole('Viewer');

    Livewire::actingAs($viewer)
        ->test('pages::budgets.index')
        ->call('openCreate')
        ->assertForbidden();
});
