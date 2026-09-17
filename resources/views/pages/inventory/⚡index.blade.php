<?php

use App\Models\ActivityLog;
use App\Models\SparePart;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('قطع الغيار')] class extends Component {
    public string $search = '';

    public bool $showLowStockOnly = false;

    public ?int $supplierFilter = null;

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public bool $showStockForm = false;

    public ?int $sparePartId = null;

    public ?int $deletingSparePartId = null;

    public ?int $stockSparePartId = null;

    public ?int $supplierId = null;

    public string $name = '';

    public string $code = '';

    public string $unit = 'piece';

    public int $quantityInStock = 0;

    public int $minimumQuantity = 0;

    public ?string $unitPrice = null;

    public int $stockAdjustment = 1;

    public string $stockAdjustmentType = 'add';

    #[Computed]
    public function spareParts()
    {
        return SparePart::query()
            ->with('supplier')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%'))
            ->when($this->showLowStockOnly, function ($query) {
                $query->whereColumn('quantity_in_stock', '<=', 'minimum_quantity');
            })
            ->when($this->supplierFilter, fn ($query) => $query->where('supplier_id', $this->supplierFilter))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function suppliers()
    {
        return Supplier::query()->orderBy('name')->get();
    }

    #[Computed]
    public function units()
    {
        return [
            'piece' => __('قطعة'),
            'box' => __('علبة'),
            'kg' => __('كجم'),
            'meter' => __('متر'),
            'liter' => __('لتر'),
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'showLowStockOnly', 'supplierFilter']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', SparePart::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(SparePart $sparePart): void
    {
        $this->authorize('update', $sparePart);

        $this->sparePartId = $sparePart->id;
        $this->supplierId = $sparePart->supplier_id;
        $this->name = $sparePart->name;
        $this->code = $sparePart->code;
        $this->unit = $sparePart->unit;
        $this->quantityInStock = $sparePart->quantity_in_stock;
        $this->minimumQuantity = $sparePart->minimum_quantity;
        $this->unitPrice = $sparePart->unit_price;
        $this->showForm = true;
    }

    protected function resetForm(): void
    {
        $this->reset(['sparePartId', 'supplierId', 'name', 'code', 'unitPrice']);
        $this->unit = 'piece';
        $this->quantityInStock = 0;
        $this->minimumQuantity = 0;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->sparePartId === null) {
            $this->authorize('create', SparePart::class);
        } else {
            $this->authorize('update', SparePart::findOrFail($this->sparePartId));
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('spare_parts', 'code')->ignore($this->sparePartId)],
            'unit' => ['required', 'string', 'max:50'],
            'quantityInStock' => ['required', 'integer', 'min:0'],
            'minimumQuantity' => ['required', 'integer', 'min:0'],
            'unitPrice' => ['nullable', 'numeric', 'min:0'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $data = [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'unit' => $validated['unit'],
            'quantity_in_stock' => $validated['quantityInStock'],
            'minimum_quantity' => $validated['minimumQuantity'],
            'unit_price' => $validated['unitPrice'] ?? 0,
            'supplier_id' => $validated['supplierId'],
        ];

        if ($this->sparePartId === null) {
            $part = SparePart::create($data);
            ActivityLog::record('created', $part);
            Flux::toast(variant: 'success', text: __('تم إنشاء القطعة.'));
        } else {
            $part = SparePart::findOrFail($this->sparePartId);
            $part->update($data);
            ActivityLog::record('updated', $part);
            Flux::toast(variant: 'success', text: __('تم تحديث القطعة.'));
        }

        $this->closeForm();
    }

    public function askDelete(SparePart $sparePart): void
    {
        $this->authorize('delete', $sparePart);

        $this->deletingSparePartId = $sparePart->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $part = SparePart::findOrFail($this->deletingSparePartId);

        $this->authorize('delete', $part);

        $part->delete();

        ActivityLog::record('deleted', $part);

        Flux::toast(variant: 'success', text: __('تم حذف القطعة.'));

        $this->confirmingDelete = false;
        $this->deletingSparePartId = null;
    }

    public function openStockForm(SparePart $sparePart): void
    {
        $this->authorize('adjustStock', $sparePart);

        $this->stockSparePartId = $sparePart->id;
        $this->stockAdjustment = 1;
        $this->stockAdjustmentType = 'add';
        $this->showStockForm = true;
    }

    public function adjustStock(): void
    {
        $part = SparePart::findOrFail($this->stockSparePartId);

        $this->authorize('adjustStock', $part);

        $validated = $this->validate([
            'stockAdjustment' => ['required', 'integer', 'min:1', 'max:100000'],
            'stockAdjustmentType' => ['required', Rule::in(['add', 'remove'])],
        ]);

        if ($validated['stockAdjustmentType'] === 'add') {
            $part->increment('quantity_in_stock', $validated['stockAdjustment']);
        } else {
            $part->decrement('quantity_in_stock', $validated['stockAdjustment']);
        }

        ActivityLog::record("stock_{$validated['stockAdjustmentType']}:{$validated['stockAdjustment']}", $part);

        Flux::toast(variant: 'success', text: __('تم تحديث المخزون.'));

        $this->showStockForm = false;
        $this->stockSparePartId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('قطع الغيار') }}</flux:heading>
            <flux:subheading>{{ __('إدارة المخزون وقطع الغيار') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إضافة قطعة') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالاسم أو الرمز...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="supplierFilter" class="md:w-48">
            <flux:select.option value="">{{ __('كل الموردين') }}</flux:select.option>
            @foreach ($this->suppliers as $supplier)
                <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:switch wire:model.live="showLowStockOnly" label="{{ __('نفاد المخزون فقط') }}" />
        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->spareParts">
            <flux:table.columns>
                <flux:table.column>{{ __('القطعة') }}</flux:table.column>
                <flux:table.column>{{ __('الكود') }}</flux:table.column>
                <flux:table.column>{{ __('المورد') }}</flux:table.column>
                <flux:table.column>{{ __('المخزون') }}</flux:table.column>
                <flux:table.column>{{ __('السعر') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->spareParts as $part)
                    <flux:table.row :key="$part->id" wire:key="part-{{ $part->id }}">
                        <flux:table.cell class="font-medium">
                            <div class="flex items-center gap-2">
                                {{ $part->name }}
                                @if ($part->isBelowMinimum())
                                    <flux:badge size="sm" color="danger">{{ __('منخفض') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono">{{ $part->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $part->supplier?->name ?? __('—') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$part->isBelowMinimum() ? 'danger' : 'success'">
                                {{ $part->quantity_in_stock }} {{ $this->units[$part->unit] ?? $part->unit }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($part->unit_price, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="arrow-path" title="{{ __('تعديل المخزون') }}" wire:click="openStockForm({{ $part->id }})" />
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $part->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $part->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد قطع غيار') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('أضف قطع الغيار إلى المخزون.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $sparePartId === null ? __('إضافة قطعة غيار') : __('تعديل القطعة') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات القطعة ومعلومات المخزون.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('اسم القطعة') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="{{ __('مثال: فلتر زيت') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الكود') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="code" placeholder="{{ __('مثال: SP-0001') }}" />
                <flux:error name="code" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الوحدة') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="unit">
                    @foreach ($this->units as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="unit" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('المورد') }}</flux:label>
                <flux:select wire:model="supplierId">
                    <flux:select.option value="">{{ __('— بدون مورد —') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الكمية بالمخزون') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="quantityInStock" min="0" />
                <flux:error name="quantityInStock" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الحد الأدنى') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="minimumQuantity" min="0" />
                <flux:error name="minimumQuantity" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('سعر الوحدة') }}</flux:label>
                <flux:input type="number" step="0.01" wire:model="unitPrice" />
                <flux:error name="unitPrice" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="closeForm">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('حفظ') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showStockForm" class="min-w-sm">
        <flux:heading size="lg">{{ __('تعديل المخزون') }}</flux:heading>
        <flux:subheading>{{ __('أضف أو اخصم كمية من المخزون الحالي.') }}</flux:subheading>

        <div class="mt-6 grid gap-4">
            <flux:field>
                <flux:label>{{ __('العملية') }}</flux:label>
                <flux:radio.group wire:model="stockAdjustmentType">
                    <flux:radio value="add" label="{{ __('إضافة') }}" />
                    <flux:radio value="remove" label="{{ __('خصم') }}" />
                </flux:radio.group>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الكمية') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="stockAdjustment" min="1" />
                <flux:error name="stockAdjustment" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('showStockForm', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="adjustStock">{{ __('حفظ') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="confirmingDelete" class="min-w-sm">
        <flux:heading size="lg">{{ __('حذف القطعة') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذه القطعة؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>