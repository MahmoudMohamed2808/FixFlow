<?php

use App\Models\ActivityLog;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderComment;
use App\Models\WorkOrderLabor;
use App\Models\WorkOrderPart;
use App\Notifications\WorkOrderNotification;
use App\Services\NotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('تفاصيل أمر الشغل')] class extends Component {
    use WithFileUploads;

    public WorkOrder $order;

    public ?int $addingPartId = null;

    public int $quantityUsed = 1;

    public ?int $addingLaborUserId = null;

    public ?float $laborHours = null;

    public ?float $laborRate = null;

    public string $comment = '';

    public $attachment = null;

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
    public function spareParts()
    {
        return SparePart::query()->with('supplier')->orderBy('name')->get();
    }

    #[Computed]
    public function selectedPart()
    {
        return $this->addingPartId ? SparePart::find($this->addingPartId) : null;
    }

    #[Computed]
    public function technicians()
    {
        $users = User::query()->where('is_active', true);

        try {
            $users->role('Technician');
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist) {
            return collect();
        }

        return $users->orderBy('name')->get();
    }

    public function updatedAddingLaborUserId(): void
    {
        $technician = $this->addingLaborUserId ? User::find($this->addingLaborUserId) : null;

        $this->laborRate = $technician?->hourly_rate !== null ? (float) $technician->hourly_rate : null;
    }

    public function mount(WorkOrder $workOrder): void
    {
        $this->order = $workOrder->load(['asset', 'assignedTo', 'reportedBy', 'comments.user', 'parts.sparePart', 'laborEntries.technician', 'attachments']);
        $this->authorize('view', $this->order);
    }

    public function updateStatus(string $newStatus): void
    {
        $this->authorize('updateStatus', $this->order);

        abort_unless(in_array($newStatus, ['open', 'assigned', 'in_progress', 'completed', 'cancelled'], true), 422);

        $data = ['status' => $newStatus];

        if ($newStatus === 'in_progress' && ! $this->order->started_at) {
            $data['started_at'] = now();
        }

        if ($newStatus === 'completed') {
            $data['completed_at'] = now();
            // التكلفة الإجمالية = قطع الغيار + أجور الفنيين (محسوبة من العلاقات مباشرة).
            $data['cost'] = $this->order->parts->sum(fn ($part) => $part->quantity_used * $part->unit_cost)
                + $this->order->laborEntries->sum('cost');
        }

        $this->order->update($data);

        ActivityLog::record("update_status:{$newStatus}", $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::STATUS_CHANGED, meta: ['status' => $newStatus, 'status_label' => $this->statuses[$newStatus]['label'] ?? $newStatus]);

        $this->order = $this->order->fresh(['asset', 'assignedTo', 'reportedBy', 'comments.user', 'parts.sparePart', 'laborEntries.technician', 'attachments']);

        Flux::toast(variant: 'success', text: __('تم تحديث الحالة.'));
    }

    public function addComment(): void
    {
        $this->authorize('comment', $this->order);

        $this->comment = trim($this->comment);

        $this->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        WorkOrderComment::create([
            'work_order_id' => $this->order->id,
            'user_id' => auth()->id(),
            'comment' => $this->comment,
        ]);

        ActivityLog::record('commented', $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::COMMENTED, meta: ['comment' => $this->comment]);

        $this->comment = '';
    }

    public function addPart(): void
    {
        $this->authorize('update', $this->order);

        $this->validate([
            'addingPartId' => ['required', 'integer', 'exists:spare_parts,id'],
            'quantityUsed' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $part = $this->selectedPart;

        if ($part->quantity_in_stock < $this->quantityUsed) {
            Flux::toast(variant: 'danger', text: __('الكمية المتاحة لا تكفي في المخزون.'));

            return;
        }

        $this->order->parts()->create([
            'spare_part_id' => $part->id,
            'quantity_used' => $this->quantityUsed,
            'unit_cost' => $part->unit_price,
        ]);

        ActivityLog::record("added_part:{$part->id}", $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::PART_ADDED, meta: ['part_name' => $part->name, 'quantity' => $this->quantityUsed]);

        $freshPart = $part->fresh();

        if ($freshPart !== null && $freshPart->isBelowMinimum()) {
            app(NotificationService::class)->notifyLowStock($freshPart);
        }

        Flux::toast(variant: 'success', text: __('تمت إضافة القطعة وحساب التكلفة.'));

        $this->reset('addingPartId', 'quantityUsed');
    }

    public function addLabor(): void
    {
        $this->authorize('updateStatus', $this->order);

        $this->validate([
            'addingLaborUserId' => ['required', 'integer', 'exists:users,id'],
            'laborHours' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'laborRate' => ['required', 'numeric', 'min:0', 'max:999999'],
        ]);

        $this->order->laborEntries()->create([
            'user_id' => $this->addingLaborUserId,
            'hours_spent' => $this->laborHours,
            'hourly_rate' => $this->laborRate,
        ]);

        ActivityLog::record("added_labor:{$this->addingLaborUserId}", $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::LABOR_ADDED, meta: [
            'hours' => $this->laborHours,
            'technician_name' => User::find($this->addingLaborUserId)?->name ?? '',
        ]);

        Flux::toast(variant: 'success', text: __('تم تسجيل ساعات العمل وحساب التكلفة.'));

        $this->reset('addingLaborUserId', 'laborHours', 'laborRate');
        $this->order->refresh(['asset', 'assignedTo', 'reportedBy', 'comments.user', 'parts.sparePart', 'laborEntries.technician', 'attachments']);
    }

    public function deleteLabor(WorkOrderLabor $labor): void
    {
        $this->authorize('update', $this->order);

        abort_unless($labor->work_order_id === $this->order->id, 404);

        $labor->delete();

        ActivityLog::record('deleted_labor', $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::LABOR_DELETED);

        Flux::toast(variant: 'success', text: __('تم حذف قيد ساعات العمل.'));

        $this->order->refresh(['asset', 'assignedTo', 'reportedBy', 'comments.user', 'parts.sparePart', 'laborEntries.technician', 'attachments']);
    }

    public function uploadAttachment(): void
    {
        $this->authorize('update', $this->order);

        $this->validate([
            'attachment' => ['required', 'file', 'max:5120'],
        ]);

        $path = $this->attachment->store('work-order-attachments', 'public');

        $this->order->attachments()->create([
            'file_path' => $path,
        ]);

        ActivityLog::record('attachment_uploaded', $this->order);
        app(NotificationService::class)->workOrder($this->order, WorkOrderNotification::ATTACHMENT_UPLOADED);

        Flux::toast(variant: 'success', text: __('تم رفع المرفق.'));

        $this->reset('attachment');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-right" :href="route('work-orders.index')" wire:navigate aria-label="{{ __('رجوع') }}" />
            <div>
                <flux:heading>{{ $this->order->code }}</flux:heading>
                <flux:subheading>
                    {{ $this->order->asset->name }}
                    @if ($this->order->scheduled_date)
                        <span class="mx-1">•</span>{{ $this->order->scheduled_date->translatedFormat('d M Y H:i') }}
                    @endif
                </flux:subheading>
            </div>
        </div>
        <flux:badge size="lg" :color="$this->statuses[$this->order->status]['color']">
            {{ $this->statuses[$this->order->status]['label'] }}
        </flux:badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <flux:card>
                <flux:heading size="lg">{{ __('تفاصيل الأمر') }}</flux:heading>
                <flux:separator class="mt-3" />
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <flux:text size="xs">{{ __('النوع') }}</flux:text>
                        <flux:text>{{ $this->order->type === 'preventive' ? __('وقائية') : __('إصلاحية') }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="xs">{{ __('الأولوية') }}</flux:text>
                        <flux:text class="capitalize">{{ $this->order->priority }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="xs">{{ __('أبلغ بواسطة') }}</flux:text>
                        <flux:text>{{ $this->order->reportedBy?->name ?? '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="xs">{{ __('الفني') }}</flux:text>
                        <flux:text>{{ $this->order->assignedTo?->name ?? '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="xs">{{ __('التكلفة الإجمالية') }}</flux:text>
                        <flux:heading size="md">{{ number_format((float) $this->order->cost, 2) }} ₪</flux:heading>
                        <flux:text size="xs">{{ __('قطع') }}: {{ number_format($this->order->parts_cost, 2) }} • {{ __('أجور') }}: {{ number_format($this->order->labor_cost, 2) }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="xs">{{ __('بدأ التنفيذ') }}</flux:text>
                        <flux:text>{{ $this->order->started_at?->translatedFormat('d M Y H:i') ?? '—' }}</flux:text>
                    </div>
                    @if ($this->order->description)
                        <div class="col-span-2">
                            <flux:text size="xs">{{ __('الوصف') }}</flux:text>
                            <flux:text>{{ $this->order->description }}</flux:text>
                        </div>
                    @endif
                </div>

                <flux:separator class="my-4" />

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->statuses as $status => $info)
                        @if ($status !== $this->order->status)
                            <flux:button variant="outline" size="sm" wire:click="updateStatus('{{ $status }}')">
                                {{ $info['label'] }}
                            </flux:button>
                        @endif
                    @endforeach
                </div>
            </flux:card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <flux:card>
                <flux:heading size="lg">{{ __('قطع الغيار') }}</flux:heading>
                <flux:separator class="mt-3" />

                <div class="mt-4 space-y-2">
                    @forelse ($this->order->parts as $part)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div>
                                <flux:text class="font-medium">{{ $part->sparePart->name }}</flux:text>
                                <flux:text size="xs">{{ $part->quantity_used }} × {{ number_format((float) $part->unit_cost, 2) }} ₪</flux:text>
                            </div>
                            <flux:text class="font-medium">{{ number_format($part->quantity_used * (float) $part->unit_cost, 2) }} ₪</flux:text>
                        </div>
                    @empty
                        <flux:text>{{ __('لا توجد قطع غيار مسجلة.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $this->order)
                    <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700 md:flex-row md:items-end">
                        <flux:field class="flex-1">
                            <flux:label>{{ __('إضافة قطعة غيار') }}</flux:label>
                            <flux:select wire:model="addingPartId">
                                <flux:select.option value="">{{ __('— اختر القطعة —') }}</flux:select.option>
                                @foreach ($this->spareParts as $part)
                                    <flux:select.option value="{{ $part->id }}">
                                        {{ $part->name }} ({{ $part->quantity_in_stock }} {{ $part->unit }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="addingPartId" />
                        </flux:field>
                        <flux:field class="w-28">
                            <flux:label>{{ __('الكمية') }}</flux:label>
                            <flux:input type="number" wire:model="quantityUsed" min="1" />
                            <flux:error name="quantityUsed" />
                        </flux:field>
                        <flux:button variant="primary" icon="plus" wire:click="addPart">{{ __('إضافة') }}</flux:button>
                    </div>
                @endcan
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('ساعات العمل') }}</flux:heading>
                <flux:separator class="mt-3" />

                <div class="mt-4 space-y-2">
                    @forelse ($this->order->laborEntries as $entry)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div>
                                <flux:text class="font-medium">{{ $entry->technician?->name ?? '—' }}</flux:text>
                                <flux:text size="xs">{{ $entry->hours_spent }} {{ __('ساعة') }} × {{ number_format((float) $entry->hourly_rate, 2) }} ₪</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ number_format((float) $entry->cost, 2) }} ₪</flux:text>
                                @can('update', $this->order)
                                    <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="deleteLabor({{ $entry->id }})" />
                                @endcan
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('لا توجد ساعات عمل مسجلة.') }}</flux:text>
                    @endforelse
                </div>

                @can('updateStatus', $this->order)
                    <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700 md:flex-row md:items-end">
                        <flux:field class="flex-1">
                            <flux:label>{{ __('الفني') }}</flux:label>
                            <flux:select wire:model.live="addingLaborUserId">
                                <flux:select.option value="">{{ __('— اختر الفني —') }}</flux:select.option>
                                @foreach ($this->technicians as $technician)
                                    <flux:select.option value="{{ $technician->id }}">
                                        {{ $technician->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="addingLaborUserId" />
                        </flux:field>
                        <flux:field class="w-28">
                            <flux:label>{{ __('الساعات') }}</flux:label>
                            <flux:input type="number" step="0.25" min="0.01" wire:model="laborHours" />
                            <flux:error name="laborHours" />
                        </flux:field>
                        <flux:field class="w-32">
                            <flux:label>{{ __('أجر الساعة') }}</flux:label>
                            <flux:input type="number" step="0.01" min="0" wire:model="laborRate" />
                            <flux:error name="laborRate" />
                        </flux:field>
                        <flux:button variant="primary" icon="plus" wire:click="addLabor">{{ __('تسجيل') }}</flux:button>
                    </div>
                @endcan
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('المرفقات') }}</flux:heading>
                <flux:separator class="mt-3" />

                <div class="mt-4 space-y-2">
                    @forelse ($this->order->attachments as $attachment)
                        <a href="{{ Storage::url($attachment->file_path) }}" target="_blank" class="block rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <flux:text class="flex items-center gap-2 font-medium">
                                <flux:icon icon="paper-clip" class="size-4" />
                                {{ basename($attachment->file_path) }}
                            </flux:text>
                        </a>
                    @empty
                        <flux:text>{{ __('لا توجد مرفقات.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $this->order)
                    <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700 md:flex-row md:items-end">
                        <flux:field class="flex-1">
                            <flux:label>{{ __('رفع مرفق') }}</flux:label>
                            <flux:input type="file" wire:model="attachment" />
                            <flux:error name="attachment" />
                        </flux:field>
                        <flux:button variant="primary" icon="arrow-up-tray" wire:click="uploadAttachment">{{ __('رفع') }}</flux:button>
                    </div>
                @endcan
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('التعليقات') }}</flux:heading>
                <flux:separator class="mt-3" />

                <div class="mt-4 space-y-4">
                    @forelse ($this->order->comments as $comment)
                        <div class="flex gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-content text-xs font-semibold text-white">
                                {{ str()->upper(substr($comment->user->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                                <div class="flex items-center justify-between">
                                    <flux:text class="font-medium">{{ $comment->user->name }}</flux:text>
                                    <flux:text size="xs">{{ $comment->created_at->diffForHumans() }}</flux:text>
                                </div>
                                <flux:text class="mt-1">{{ $comment->comment }}</flux:text>
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('لا توجد تعليقات بعد.') }}</flux:text>
                    @endforelse
                </div>

                @can('comment', $this->order)
                    <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:textarea wire:model="comment" rows="2" placeholder="{{ __('اكتب تعليقًا...') }}" />
                        <flux:error name="comment" />
                        <div class="flex justify-end">
                            <flux:button variant="primary" wire:click="addComment">{{ __('إضافة تعليق') }}</flux:button>
                        </div>
                    </div>
                @endcan
            </flux:card>
        </div>
    </div>
</div>