<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\PreventiveSchedule;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الصيانة الوقائية')] class extends Component {
    public string $search = '';

    public bool $showActiveOnly = false;

    public string $filterFrequency = '';

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $scheduleId = null;

    public ?int $deletingScheduleId = null;

    public ?int $assetId = null;

    public string $title = '';

    public string $frequencyType = 'monthly';

    public int $frequencyValue = 1;

    public ?string $nextDueDate = null;

    public bool $isActive = true;

    #[Computed]
    public function schedules()
    {
        return PreventiveSchedule::query()
            ->with('asset')
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%')->orWhereHas('asset', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')))
            ->when($this->showActiveOnly, fn ($query) => $query->where('is_active', true))
            ->when($this->filterFrequency, fn ($query) => $query->where('frequency_type', $this->filterFrequency))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function assets()
    {
        return Asset::query()->orderBy('name')->get();
    }

    #[Computed]
    public function frequencies()
    {
        return [
            'daily' => __('يومية'),
            'weekly' => __('أسبوعية'),
            'monthly' => __('شهرية'),
            'yearly' => __('سنوية'),
            'usage_based' => __('على أساس الاستخدام'),
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'showActiveOnly', 'filterFrequency']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', PreventiveSchedule::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(PreventiveSchedule $schedule): void
    {
        $this->authorize('update', $schedule);

        $this->scheduleId = $schedule->id;
        $this->assetId = $schedule->asset_id;
        $this->title = $schedule->title;
        $this->frequencyType = $schedule->frequency_type;
        $this->frequencyValue = $schedule->frequency_value;
        $this->nextDueDate = $schedule->next_due_date?->format('Y-m-d');
        $this->isActive = $schedule->is_active;
        $this->showForm = true;
    }

    protected function resetForm(): void
    {
        $this->reset(['scheduleId', 'assetId', 'title', 'nextDueDate']);
        $this->frequencyType = 'monthly';
        $this->frequencyValue = 1;
        $this->isActive = true;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->scheduleId === null) {
            $this->authorize('create', PreventiveSchedule::class);
        } else {
            $this->authorize('update', PreventiveSchedule::findOrFail($this->scheduleId));
        }

        $validated = $this->validate([
            'assetId' => ['required', 'integer', 'exists:assets,id'],
            'title' => ['required', 'string', 'max:255'],
            'frequencyType' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly', 'usage_based'])],
            'frequencyValue' => ['required', 'integer', 'min:1', 'max:3650'],
            'nextDueDate' => ['required', 'date'],
            'isActive' => ['boolean'],
        ]);

        $data = [
            'asset_id' => $validated['assetId'],
            'title' => $validated['title'],
            'frequency_type' => $validated['frequencyType'],
            'frequency_value' => $validated['frequencyValue'],
            'next_due_date' => $validated['nextDueDate'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->scheduleId === null) {
            $schedule = PreventiveSchedule::create($data);
            ActivityLog::record('created', $schedule);
            Flux::toast(variant: 'success', text: __('تم إنشاء الجدول الوقائي.'));
        } else {
            $schedule = PreventiveSchedule::findOrFail($this->scheduleId);
            $schedule->update($data);
            ActivityLog::record('updated', $schedule);
            Flux::toast(variant: 'success', text: __('تم تحديث الجدول.'));
        }

        $this->closeForm();
    }

    public function toggleActive(PreventiveSchedule $schedule): void
    {
        $this->authorize('update', $schedule);

        $schedule->update(['is_active' => ! $schedule->is_active]);

        ActivityLog::record($schedule->is_active ? 'activated' : 'deactivated', $schedule);
    }

    public function askDelete(PreventiveSchedule $schedule): void
    {
        $this->authorize('delete', $schedule);

        $this->deletingScheduleId = $schedule->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $schedule = PreventiveSchedule::findOrFail($this->deletingScheduleId);

        $this->authorize('delete', $schedule);

        $schedule->delete();

        ActivityLog::record('deleted', $schedule);

        Flux::toast(variant: 'success', text: __('تم حذف الجدول.'));

        $this->confirmingDelete = false;
        $this->deletingScheduleId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الصيانة الوقائية') }}</flux:heading>
            <flux:subheading>{{ __('جدولة الصيانة الدورية') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إنشاء جدول') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالعنوان أو الأصل...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterFrequency" class="md:w-40">
            <flux:select.option value="">{{ __('كل الأنواع') }}</flux:select.option>
            @foreach ($this->frequencies as $type => $label)
                <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:switch wire:model.live="showActiveOnly" label="{{ __('النشطة فقط') }}" />
        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->schedules">
            <flux:table.columns>
                <flux:table.column>{{ __('العنوان') }}</flux:table.column>
                <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                <flux:table.column>{{ __('النوع') }}</flux:table.column>
                <flux:table.column>{{ __('الموعد القادم') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->schedules as $schedule)
                    <flux:table.row :key="$schedule->id" wire:key="sch-{{ $schedule->id }}">
                        <flux:table.cell class="font-medium">{{ $schedule->title }}</flux:table.cell>
                        <flux:table.cell>{{ $schedule->asset->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="info">
                                {{ $this->frequencies[$schedule->frequency_type] }}
                                ({{ $schedule->frequency_value }})
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($schedule->next_due_date->isPast() && $schedule->is_active)
                                <flux:badge size="sm" color="danger">{{ $schedule->next_due_date->translatedFormat('d M Y') }}</flux:badge>
                            @else
                                {{ $schedule->next_due_date->translatedFormat('d M Y') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$schedule->is_active ? 'success' : 'secondary'">
                                {{ $schedule->is_active ? __('نشط') : __('مُعطّل') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" :icon="$schedule->is_active ? 'pause' : 'play'" title="{{ __('تبديل الحالة') }}" wire:click="toggleActive({{ $schedule->id }})" />
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $schedule->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $schedule->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد جداول وقائية') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('أنشئ جدول صيانة دوريًا لكل أصل.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $scheduleId === null ? __('إنشاء جدول وقائي') : __('تعديل الجدول') }}</flux:heading>
        <flux:subheading>{{ __('حدد الأصل وتكرار الصيانة الدورية والموعد القادم.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('العنوان') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="title" placeholder="{{ __('مثال: تزييت شهري') }}" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الأصل') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="assetId">
                    <flux:select.option value="">{{ __('— اختر الأصل —') }}</flux:select.option>
                    @foreach ($this->assets as $asset)
                        <flux:select.option value="{{ $asset->id }}">{{ $asset->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="assetId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('نوع التكرار') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="frequencyType">
                    @foreach ($this->frequencies as $type => $label)
                        <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="frequencyType" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('قيمة التكرار') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="frequencyValue" min="1" />
                <flux:error name="frequencyValue" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الموعد القادم') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="nextDueDate" />
                <flux:error name="nextDueDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الحالة') }}</flux:label>
                <flux:switch wire:model="isActive" label="{{ __('نشط') }}" />
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
        <flux:heading size="lg">{{ __('حذف الجدول') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا الجدول الوقائي؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>