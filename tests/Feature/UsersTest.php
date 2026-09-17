<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

it('lets an admin create a user with a role', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $role = Role::where('name', 'Technician')->first();
    $department = Department::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openCreate')
        ->set('name', 'فني جديد')
        ->set('email', 'tech@fixflow.test')
        ->set('password', 'secret123')
        ->set('password_confirmation', 'secret123')
        ->set('departmentId', $department->id)
        ->set('roleId', $role->id)
        ->assertHasNoErrors()
        ->call('save');

    $user = User::where('email', 'tech@fixflow.test')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('Technician'))->toBeTrue();
    expect($user->department_id)->toBe($department->id);
    expect($user->is_active)->toBeTrue();
});

it('requires a password and matching confirmation when creating a user', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $role = Role::where('name', 'Viewer')->first();

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openCreate')
        ->set('name', 'ناقص')
        ->set('email', 'incomplete@fixflow.test')
        ->set('roleId', $role->id)
        ->call('save')
        ->assertHasErrors(['password']);
});

it('lets an admin update a user and change their role', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $user = User::factory()->create(['name' => 'اسم قديم']);
    $user->assignRole('Viewer');
    $role = Role::where('name', 'Supervisor')->first();

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openEdit', $user->id)
        ->set('name', 'اسم جديد')
        ->set('roleId', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('اسم جديد');
    expect($user->fresh()->hasRole('Supervisor'))->toBeTrue();
    expect($user->fresh()->hasRole('Viewer'))->toBeFalse();
});

it('lets an admin deactivate a user', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $user = User::factory()->create(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('toggleActive', $user->id);

    expect($user->fresh()->is_active)->toBeFalse();
});

it('does not let an admin deactivate themselves', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('toggleActive', $admin->id)
        ->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('lets an admin delete a user', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('askDelete', $user->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(User::find($user->id))->toBeNull();
});

it('lists users with their roles', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $user = User::factory()->create(['name' => 'مستخدم للبحث']);
    $user->assignRole('Technician');

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->set('search', 'مستخدم للبحث')
        ->assertSee('مستخدم للبحث')
        ->assertSee('Technician');
});
