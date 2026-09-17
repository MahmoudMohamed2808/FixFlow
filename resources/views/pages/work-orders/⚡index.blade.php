<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderNotification;
use App\Services\NotificationService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('أوامر الشغل')] class extends Component {
    public string $search = '';

    public string $filterStatus = '';

    public string $filterType = '';

    public string $filterPriority = '';

    public ?int $filterAssetId = null;

    public bool $showForm = false;

    public bool $showAssign = false;

    public bool $confirmingDelete = false;

    public ?int $workOrderId = null;

    public ?int $deletingWorkOrderId = null;

    public ?int $assigningOrderId = null;

    public ?int $assignedTo = null;

    public ?int $assetId = null;

    public string $type = 'corrective';

    public string $priority = 'medium';

    public string $status = 'open';

    public ?string $description = null;

    public ?string $scheduledDate = null;

    #[Computed]
    public function workOrders()
    {
        return WorkOrder::query()
            ->with(['asset', 'assignedTo'])
            ->when($this->search, fn ($query) => $query->where('code', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%'))
            ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterType, fn ($query) => $query->where('type', $this->filterType))
            ->when($this->filterPriority, fn ($query) => $query->where('priority', $this->filterPriority))
            ->when($this->filterAssetId, fn ($query) => $query->where('asset_id', $this->filterAssetId))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function assets()
    {
        return Asset::query()->orderBy('name')->get();
    }

    #[Computed]
    public function technicians()
    {
        $users = \App\Models\User::query();

        try {
            $users->role('Technician');
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            // لا توجد أدوار مُعدة بعد — أعد قائمة فارغة بدلًا من تعطيل الصفحة.
            return collect();
        }

        return $users->orderBy('name')->get();
    }

    #[Computed]
    public function statuses()
    {
        return [
            'open' => ['label' => __('مفتوحة'), 'color' => 'warning'],
            'assigned' => ['label' => __('تم التعيين'), 'color' => 'info'],
            'in_progress' => ['label' => __('قيد التنفيذ'), 'color' => 'info'],
            'completed' => ['label' => __('مكتملة'), 'color' => 'success'],
            'cancelled' => ['label' => __('ملغاة'), 'color' => 'danger'],
        ];
    }

    #[Computed]
    public function priorities()
    {
        return [
            'low' => ['label' => __('منخفضة'), 'color' => 'secondary'],
            'medium' => ['label' => __('متوسطة'), 'color' => 'info'],
            'high' => ['label' => __('عالية'), 'color' => 'warning'],
            'urgent' => ['label' => __('عاجلة'), 'color' => 'danger'],
        ];
    }

    #[Computed]
    public function types()
    {
        return [
            'corrective' => __('إصلاحية'),
            'preventive' => __('وقائية'),
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterStatus', 'filterType', 'filterPriority', 'filterAssetId']);
    }

    #[On('refresh-work-orders')]
    public function refreshList(): void
    {
        unset($this->workOrders);
    }

    public function openCreate(): void
    {
        $this->authorize('create', WorkOrder::class);

        $this->resetForm();
        $this->showForm = true;
    }

    protected function resetForm(): void
    {
        $this->reset(['workOrderId', 'assetId', 'description', 'scheduledDate']);
        $this->type = 'corrective';
        $this->priority = 'medium';
        $this->status = 'open';
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorize('create', WorkOrder::class);

        $validated = $this->validate([
            'assetId' => ['required', 'integer', 'exists:assets,id'],
            'type' => ['required', 'in:preventive,corrective'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'description' => ['required', 'string', 'max:2000'],
            'scheduledDate' => ['nullable', 'date'],
        ]);

        $order = WorkOrder::create([
            'asset_id' => $validated['assetId'],
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'description' => $validated['description'],
            'scheduled_date' => $validated['scheduledDate'],
            'reported_by' => auth()->id(),
            'status' => 'open',
        ]);

        ActivityLog::record('created', $order);
        app(NotificationService::class)->workOrder($order, WorkOrderNotification::CREATED);

        Flux::toast(variant: 'success', text: __('تم إنشاء أمر الشغل بنجاح.'));

        $this->closeForm();
    }

    public function openAssign(WorkOrder $workOrder): void
    {
        $this->authorize('assign', $workOrder);

        $this->assigningOrderId = $workOrder->id;
        $this->assignedTo = $workOrder->assigned_to;
        $this->showAssign = true;
    }

    public function saveAssignment(): void
    {
        $order = WorkOrder::findOrFail($this->assigningOrderId);

        $this->authorize('assign', $order);

        $this->validate([
            'assignedTo' => ['required', 'integer', 'exists:users,id'],
        ]);

        $order->update([
            'assigned_to' => $this->assignedTo,
            'status' => $order->status === 'open' ? 'assigned' : $order->status,
        ]);

        ActivityLog::record("assigned:{$this->assignedTo}", $order);
        app(NotificationService::class)->workOrder($order, WorkOrderNotification::ASSIGNED, meta: ['assigned_to' => (int) $this->assignedTo]);

        Flux::toast(variant: 'success', text: __('تم تعيين الفني بنجاح.'));

        $this->showAssign = false;
        $this->assigningOrderId = null;
        $this->assignedTo = null;
    }

    public function askDelete(WorkOrder $workOrder): void
    {
        $this->authorize('delete', $workOrder);

        $this->deletingWorkOrderId = $workOrder->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $order = WorkOrder::findOrFail($this->deletingWorkOrderId);

        $this->authorize('delete', $order);

        $order->delete();

        ActivityLog::record('deleted', $order);
        app(NotificationService::class)->workOrderDeleted($order);

        Flux::toast(variant: 'success', text: __('تم حذف أمر الشغل.'));

        $this->confirmingDelete = false;
        $this->deletingWorkOrderId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('أوامر الشغل') }}</flux:heading>
            <flux:subheading>{{ __('إدارة أوامر الشغل') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إنشاء أمر شغل') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 xl:flex-row xl:items-center">
        <flux:input.group class="w-full xl:w-60">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالكود أو الوصف...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterStatus" class="xl:w-40">
            <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
            @foreach ($this->statuses as $status => $info)
                <flux:select.option value="{{ $status }}">{{ $info['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterType" class="xl:w-40">
            <flux:select.option value="">{{ __('كل الأنواع') }}</flux:select.option>
            @foreach ($this->types as $type => $label)
                <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterPriority" class="xl:w-40">
            <flux:select.option value="">{{ __('كل الأولويات') }}</flux:select.option>
            @foreach ($this->priorities as $priority => $info)
                <flux:select.option value="{{ $priority }}">{{ $info['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterAssetId" class="xl:w-52">
            <flux:select.option value="">{{ __('كل الأصول') }}</flux:select.option>
            @foreach ($this->assets as $asset)
                <flux:select.option value="{{ $asset->id }}">{{ $asset->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->workOrders">
            <flux:table.columns>
                <flux:table.column>{{ __('الكود') }}</flux:table.column>
                <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                <flux:table.column>{{ __('النوع') }}</flux:table.column>
                <flux:table.column>{{ __('الأولوية') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column>{{ __('الفني') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->workOrders as $order)
                    <flux:table.row :key="$order->id" wire:key="wo-{{ $order->id }}">
                        <flux:table.cell>
                            <a href="{{ route('work-orders.show', $order) }}" wire:navigate class="font-mono text-sm font-medium text-accent hover:underline">
                                {{ $order->code }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->asset->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$order->type === 'preventive' ? 'info' : 'secondary'">
                                {{ $this->types[$order->type] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$this->priorities[$order->priority]['color']">
                                {{ $this->priorities[$order->priority]['label'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$this->statuses[$order->status]['color']">
                                {{ $this->statuses[$order->status]['label'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->assignedTo?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="eye" title="{{ __('عرض') }}" :href="route('work-orders.show', $order)" wire:navigate />
                                <flux:button variant="ghost" size="sm" icon="user-plus" title="{{ __('تعيين فني') }}" wire:click="openAssign({{ $order->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $order->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد أوامر شغل') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإنشاء أول أمر شغل أو عدّل الفلاتر.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ __('إنشاء أمر شغل جديد') }}</flux:heading>
        <flux:subheading>{{ __('اختر الأصل وحدد نوع أمر الشغل وأولوياته.') }}</flux:subheading>

        <div class="mt-6 grid gap-4">
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

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('النوع') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="type">
                        @foreach ($this->types as $type => $label)
                            <flux:select.option value="{{ $type }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('الأولوية') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="priority">
                        @foreach ($this->priorities as $priority => $info)
                            <flux:select.option value="{{ $priority }}">{{ $info['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="priority" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('الوصف / المشكلة') }} <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="description" rows="3" placeholder="{{ __('صف المشكلة أو سبب أمر الشغل...') }}" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الموعد المقرر') }}</flux:label>
                <flux:input type="datetime-local" wire:model="scheduledDate" />
                <flux:error name="scheduledDate" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="closeForm">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('إنشاء') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showAssign" class="min-w-sm">
        <flux:heading size="lg">{{ __('تعيين فني') }}</flux:heading>
        <flux:subheading>{{ __('اختر الفني المسؤول عن تنفيذ أمر الشغل.') }}</flux:subheading>

        <div class="mt-6">
            <flux:field>
                <flux:label>{{ __('الفني') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="assignedTo">
                    <flux:select.option value="">{{ __('— اختر الفني —') }}</flux:select.option>
                    @foreach ($this->technicians as $technician)
                        <flux:select.option value="{{ $technician->id }}">{{ $technician->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="assignedTo" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('showAssign', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="saveAssignment">{{ __('حفظ التعيين') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="confirmingDelete" class="min-w-sm">
        <flux:heading size="lg">{{ __('حذف أمر الشغل') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا أمر الشغل؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>