<?php

use App\Models\ActivityLog;
use App\Models\Supplier;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الموردون')] class extends Component {
    public string $search = '';

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $supplierId = null;

    public ?int $deletingSupplierId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    #[Computed]
    public function suppliers()
    {
        return Supplier::query()
            ->withCount('spareParts')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15);
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Supplier::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(Supplier $supplier): void
    {
        $this->authorize('update', $supplier);

        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone ?? '';
        $this->email = $supplier->email ?? '';
        $this->address = $supplier->address ?? '';
        $this->showForm = true;
    }

    protected function resetForm(): void
    {
        $this->reset(['supplierId', 'name', 'phone', 'email', 'address']);
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->supplierId === null) {
            $this->authorize('create', Supplier::class);
        } else {
            $this->authorize('update', Supplier::findOrFail($this->supplierId));
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
        ];

        if ($this->supplierId === null) {
            $supplier = Supplier::create($data);
            ActivityLog::record('created', $supplier);
            Flux::toast(variant: 'success', text: __('تم إنشاء المورد.'));
        } else {
            $supplier = Supplier::findOrFail($this->supplierId);
            $supplier->update($data);
            ActivityLog::record('updated', $supplier);
            Flux::toast(variant: 'success', text: __('تم تحديث المورد.'));
        }

        $this->closeForm();
    }

    public function askDelete(Supplier $supplier): void
    {
        $this->authorize('delete', $supplier);

        $this->deletingSupplierId = $supplier->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $supplier = Supplier::findOrFail($this->deletingSupplierId);

        $this->authorize('delete', $supplier);

        $supplier->delete();

        ActivityLog::record('deleted', $supplier);

        Flux::toast(variant: 'success', text: __('تم حذف المورد.'));

        $this->confirmingDelete = false;
        $this->deletingSupplierId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الموردون') }}</flux:heading>
            <flux:subheading>{{ __('إدارة الموردين') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إضافة مورد') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالاسم أو البريد أو الهاتف...') }}" />
        </flux:input.group>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح البحث') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->suppliers">
            <flux:table.columns>
                <flux:table.column>{{ __('الاسم') }}</flux:table.column>
                <flux:table.column>{{ __('الهاتف') }}</flux:table.column>
                <flux:table.column>{{ __('البريد') }}</flux:table.column>
                <flux:table.column>{{ __('قطع الغيار') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->suppliers as $supplier)
                    <flux:table.row :key="$supplier->id" wire:key="sup-{{ $supplier->id }}">
                        <flux:table.cell class="font-medium">{{ $supplier->name }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->phone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->email ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="info">{{ $supplier->spare_parts_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $supplier->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $supplier->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا يوجد موردون') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('أضف الموردين لتتبعهم بسهولة.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm">
        <flux:heading size="lg">{{ $supplierId === null ? __('إضافة مورد') : __('تعديل المورد') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات المورد.') }}</flux:subheading>

        <div class="mt-6 grid gap-4">
            <flux:field>
                <flux:label>{{ __('الاسم') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="{{ __('اسم الشركة أو المورد') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الهاتف') }}</flux:label>
                <flux:input wire:model="phone" dir="ltr" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }}</flux:label>
                <flux:input type="email" wire:model="email" dir="ltr" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('العنوان') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="closeForm">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('حفظ') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="confirmingDelete" class="min-w-sm">
        <flux:heading size="lg">{{ __('حذف المورد') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا المورد؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>