<?php

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('المستخدمون')] class extends Component {
    public string $search = '';

    public ?int $filterRoleId = null;

    public string $filterActive = '';

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $userId = null;

    public ?int $deletingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $phone = null;

    public ?int $departmentId = null;

    public ?int $roleId = null;

    public bool $isActive = true;

    #[Computed]
    public function users()
    {
        return User::query()
            ->with(['roles', 'department'])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterRoleId, function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('roles.id', $this->filterRoleId));
            })
            ->when($this->filterActive !== '', fn ($query) => $query->where('is_active', $this->filterActive === 'active'))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('name')->get();
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterRoleId', 'filterActive']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(User $user): void
    {
        $this->authorize('update', $user);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->departmentId = $user->department_id;
        $this->isActive = $user->is_active;
        $this->roleId = $user->roles->first()?->id;
        $this->password = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'userId', 'name', 'email', 'password', 'password_confirmation', 'phone', 'departmentId', 'roleId',
        ]);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->userId === null) {
            $this->authorize('create', User::class);
        } else {
            $this->authorize('update', User::findOrFail($this->userId));
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => ['required_if:userId,null', 'confirmed', 'min:8', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'isActive' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'department_id' => $validated['departmentId'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->userId === null) {
            $data['password'] = $validated['password'];
            $data['email_verified_at'] = now();

            $user = User::create($data);
            $user->syncRoles([$validated['roleId']]);
            ActivityLog::record('created', $user);
            Flux::toast(variant: 'success', text: __('تم إنشاء المستخدم بنجاح.'));
        } else {
            $user = User::findOrFail($this->userId);

            if (! empty($validated['password'])) {
                $data['password'] = $validated['password'];
            }

            $user->update($data);
            $user->syncRoles([$validated['roleId']]);
            ActivityLog::record('updated', $user);
            Flux::toast(variant: 'success', text: __('تم تحديث بيانات المستخدم.'));
        }

        $this->closeForm();
    }

    public function toggleActive(User $user): void
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record($user->is_active ? 'activated' : 'deactivated', $user);
        Flux::toast(variant: 'success', text: $user->is_active ? __('تم تفعيل الحساب.') : __('تم إيقاف الحساب.'));
    }

    public function askDelete(User $user): void
    {
        $this->authorize('delete', $user);

        $this->deletingUserId = $user->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        $this->authorize('delete', $user);

        $user->delete();

        ActivityLog::record('deleted', $user);

        Flux::toast(variant: 'success', text: __('تم حذف المستخدم.'));

        $this->confirmingDelete = false;
        $this->deletingUserId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('المستخدمون') }}</flux:heading>
            <flux:subheading>{{ __('إدارة المستخدمين والأدوار') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="user-plus" wire:click="openCreate">{{ __('إضافة مستخدم') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالاسم أو البريد...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterRoleId" class="md:w-48">
            <flux:select.option value="">{{ __('كل الأدوار') }}</flux:select.option>
            @foreach ($this->roles as $role)
                <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterActive" class="md:w-40">
            <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
            <flux:select.option value="active">{{ __('نشط') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('موقوف') }}</flux:select.option>
        </flux:select>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('المستخدم') }}</flux:table.column>
                <flux:table.column>{{ __('البريد') }}</flux:table.column>
                <flux:table.column>{{ __('القسم') }}</flux:table.column>
                <flux:table.column>{{ __('الدور') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id" wire:key="user-{{ $user->id }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                <div>
                                    <div class="font-medium text-zinc-800 dark:text-white">{{ $user->name }}</div>
                                    @if ($user->phone)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $user->phone }}</div>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-zinc-600 dark:text-zinc-300" dir="ltr">{{ $user->email }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->department?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="indigo">{{ $user->roles->first()?->name ?? '—' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->id === auth()->id())
                                <flux:badge size="sm" variant="pill" color="secondary">أنت</flux:badge>
                            @else
                                <flux:badge size="sm" variant="pill" :color="$user->is_active ? 'success' : 'danger'">
                                    {{ $user->is_active ? __('نشط') : __('موقوف') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $user->id }})" />
                                @if ($user->id !== auth()->id())
                                    <flux:button variant="ghost" size="sm" :icon="$user->is_active ? 'pause-circle' : 'play-circle'" :title="$user->is_active ? __('إيقاف الحساب') : __('تفعيل الحساب')" wire:click="toggleActive({{ $user->id }})" />
                                    <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $user->id }})" />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا يوجد مستخدمون') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإضافة أول مستخدم أو عدّل الفلاتر.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $userId === null ? __('إضافة مستخدم جديد') : __('تعديل المستخدم') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات المستخدم بالأسفل، الحقول المميزة بـ * مطلوبة.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('الاسم') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="{{ __('مثال: أحمد محمد') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="email" type="email" placeholder="{{ __('user@example.com') }}" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الهاتف') }}</flux:label>
                <flux:input wire:model="phone" placeholder="{{ __('01xxxxxxxxx') }}" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('القسم') }}</flux:label>
                <flux:select wire:model="departmentId">
                    <flux:select.option value="">{{ __('— اختر القسم —') }}</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option value="{{ $department->id }}">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="departmentId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الدور') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="roleId">
                    @foreach ($this->roles as $role)
                        <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="roleId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('كلمة المرور') }} <span class="text-red-500">@if ($userId === null)*@endif</span></flux:label>
                <flux:input wire:model="password" type="password" placeholder="{{ $userId === null ? __('8 أحرف كحد أدنى') : __('اتركها فارغة للاحتفاظ الحالية') }}" />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('تأكيد كلمة المرور') }} <span class="text-red-500">@if ($userId === null)*@endif</span></flux:label>
                <flux:input wire:model="password_confirmation" type="password" />
                <flux:error name="password" />
            </flux:field>

            <flux:switch wire:model.live="isActive" label="{{ __('الحساب نشط') }}" />
            <flux:error name="isActive" />
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="closeForm">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('حفظ') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="confirmingDelete" class="min-w-sm">
        <flux:heading size="lg">{{ __('حذف المستخدم') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا المستخدم؟ سيُفقد تعيينه على أوامر الشغل والأصول. ننصح بإيقاف الحساب بدلاً من الحذف.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>
