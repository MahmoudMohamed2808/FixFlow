<?php

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\WorkOrder;
use App\Notifications\InvoiceNotification;
use App\Services\NotificationService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الفواتير')] class extends Component {
    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterClientId = null;

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $invoiceId = null;

    public ?int $deletingInvoiceId = null;

    public ?int $clientId = null;

    public ?int $workOrderId = null;

    public ?string $issueDate = null;

    public ?string $dueDate = null;

    public string $status = 'draft';

    public ?float $tax = 0;

    public ?string $notes = null;

    #[Computed]
    public function invoices()
    {
        return Invoice::query()
            ->with(['client', 'workOrder'])
            ->when($this->search, fn ($query) => $query->where('invoice_number', 'like', '%'.$this->search.'%'))
            ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterClientId, fn ($query) => $query->where('client_id', $this->filterClientId))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function clients()
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function statuses()
    {
        return [
            'draft' => ['label' => __('مسودة'), 'color' => 'secondary'],
            'sent' => ['label' => __('مرسلة'), 'color' => 'info'],
            'paid' => ['label' => __('مدفوعة'), 'color' => 'success'],
            'overdue' => ['label' => __('متأخرة'), 'color' => 'danger'],
            'cancelled' => ['label' => __('ملغاة'), 'color' => 'slate'],
        ];
    }

    #[Computed]
    public function clientWorkOrders()
    {
        if (! $this->clientId) {
            return collect();
        }

        return WorkOrder::query()
            ->where('client_id', $this->clientId)
            ->latest()
            ->get(['id', 'code']);
    }

    public function updatedClientId(): void
    {
        $this->workOrderId = null;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterStatus', 'filterClientId']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Invoice::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(Invoice $invoice): void
    {
        $this->authorize('update', $invoice);

        $this->invoiceId = $invoice->id;
        $this->clientId = $invoice->client_id;
        $this->workOrderId = $invoice->work_order_id;
        $this->issueDate = $invoice->issue_date?->format('Y-m-d');
        $this->dueDate = $invoice->due_date?->format('Y-m-d');
        $this->status = $invoice->status;
        $this->tax = (float) $invoice->tax;
        $this->notes = $invoice->notes;
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
            'invoiceId', 'clientId', 'workOrderId', 'issueDate', 'dueDate', 'tax', 'notes',
        ]);
        $this->status = 'draft';
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->invoiceId === null) {
            $this->authorize('create', Invoice::class);
        } else {
            $this->authorize('update', Invoice::findOrFail($this->invoiceId));
        }

        $validated = $this->validate([
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'workOrderId' => ['nullable', 'integer', 'exists:work_orders,id'],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:issueDate'],
            'status' => ['required', Rule::in(['draft', 'sent', 'paid', 'overdue', 'cancelled'])],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'client_id' => $validated['clientId'],
            'work_order_id' => $validated['workOrderId'],
            'issue_date' => $validated['issueDate'],
            'due_date' => $validated['dueDate'],
            'status' => $validated['status'],
            'tax' => $validated['tax'] ?? 0,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->invoiceId === null) {
            $invoice = Invoice::create($data);
            ActivityLog::record('created', $invoice);
            app(NotificationService::class)->invoice($invoice, InvoiceNotification::CREATED);
            Flux::toast(variant: 'success', text: __('تم إنشاء الفاتورة بنجاح.'));
        } else {
            $invoice = Invoice::findOrFail($this->invoiceId);
            $invoice->update($data);

            if ($invoice->status === 'paid' && ! $invoice->paid_at) {
                $invoice->update(['paid_at' => now()]);
            }

            $invoice->recalculateTotals();
            ActivityLog::record('updated', $invoice);
            app(NotificationService::class)->invoice($invoice, InvoiceNotification::UPDATED);
            Flux::toast(variant: 'success', text: __('تم تحديث الفاتورة.'));
        }

        $this->closeForm();
    }

    public function askDelete(Invoice $invoice): void
    {
        $this->authorize('delete', $invoice);

        $this->deletingInvoiceId = $invoice->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $invoice = Invoice::findOrFail($this->deletingInvoiceId);

        $this->authorize('delete', $invoice);

        $invoice->delete();

        ActivityLog::record('deleted', $invoice);

        Flux::toast(variant: 'success', text: __('تم حذف الفاتورة.'));

        $this->confirmingDelete = false;
        $this->deletingInvoiceId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الفواتير') }}</flux:heading>
            <flux:subheading>{{ __('إدارة فواتير العملاء') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إنشاء فاتورة') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-60">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث برقم الفاتورة...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterStatus" class="md:w-40">
            <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
            @foreach ($this->statuses as $status => $info)
                <flux:select.option value="{{ $status }}">{{ $info['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterClientId" class="md:w-52">
            <flux:select.option value="">{{ __('كل العملاء') }}</flux:select.option>
            @foreach ($this->clients as $client)
                <flux:select.option value="{{ $client->id }}">{{ $client->company_name ?? $client->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('الرقم') }}</flux:table.column>
                <flux:table.column>{{ __('العميل') }}</flux:table.column>
                <flux:table.column>{{ __('أمر الشغل') }}</flux:table.column>
                <flux:table.column>{{ __('تاريخ الإصدار') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column>{{ __('الإجمالي') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->invoices as $invoice)
                    <flux:table.row :key="$invoice->id" wire:key="invoice-{{ $invoice->id }}">
                        <flux:table.cell>
                            <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="font-mono font-medium text-zinc-800 hover:underline dark:text-white">
                                {{ $invoice->invoice_number }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->client->company_name ?? $invoice->client->name }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $invoice->workOrder?->code ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->issue_date?->translatedFormat('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$this->statuses[$invoice->status]['color']">
                                {{ $this->statuses[$invoice->status]['label'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $invoice->total_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="eye" title="{{ __('عرض') }}" :href="route('invoices.show', $invoice)" wire:navigate />
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $invoice->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $invoice->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد فواتير') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإنشاء أول فاتورة أو عدّل الفلاتر.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $invoiceId === null ? __('إنشاء فاتورة جديدة') : __('تعديل الفاتورة') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات الفاتورة بالأسفل، الحقول المميزة بـ * مطلوبة.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('العميل') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model.live="clientId">
                    <flux:select.option value="">{{ __('— اختر العميل —') }}</flux:select.option>
                    @foreach ($this->clients as $client)
                        <flux:select.option value="{{ $client->id }}">{{ $client->company_name ?? $client->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="clientId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('أمر الشغل (اختياري)') }}</flux:label>
                <flux:select wire:model="workOrderId">
                    <flux:select.option value="">{{ __('— بدون أمر شغل —') }}</flux:select.option>
                    @foreach ($this->clientWorkOrders as $order)
                        <flux:select.option value="{{ $order->id }}">{{ $order->code }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="workOrderId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('تاريخ الإصدار') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="issueDate" />
                <flux:error name="issueDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('تاريخ الاستحقاق') }}</flux:label>
                <flux:input type="date" wire:model="dueDate" />
                <flux:error name="dueDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الحالة') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="status">
                    @foreach ($this->statuses as $status => $info)
                        <flux:select.option value="{{ $status }}">{{ $info['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الضريبة') }}</flux:label>
                <flux:input type="number" step="0.01" min="0" wire:model="tax" />
                <flux:error name="tax" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('ملاحظات') }}</flux:label>
                <flux:textarea wire:model="notes" rows="2" />
                <flux:error name="notes" />
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
        <flux:heading size="lg">{{ __('حذف الفاتورة') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذه الفاتورة مع كل بنودها؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>
