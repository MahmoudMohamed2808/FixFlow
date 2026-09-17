<?php

use App\Mail\InvoiceMailable;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('تفاصيل الفاتورة')] class extends Component {
    public Invoice $invoice;

    public string $itemDescription = '';

    public float $itemQuantity = 1;

    public ?float $itemUnitPrice = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['client', 'workOrder', 'items']);
        $this->authorize('view', $this->invoice);
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

    public function markSent(): void
    {
        $this->authorize('updateStatus', $this->invoice);

        if (! in_array($this->invoice->status, ['draft', 'overdue'], true)) {
            Flux::toast(variant: 'danger', text: __('لا يمكن إرسال الفاتورة في حالتها الحالية.'));

            return;
        }

        $this->invoice->update(['status' => 'sent']);

        ActivityLog::record('update_status:sent', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم إرسال الفاتورة.'));
    }

    public function markPaid(): void
    {
        $this->authorize('updateStatus', $this->invoice);

        if (! in_array($this->invoice->status, ['draft', 'sent', 'overdue'], true)) {
            Flux::toast(variant: 'danger', text: __('لا يمكن تحصيل الفاتورة في حالتها الحالية.'));

            return;
        }

        $this->invoice->markAsPaid();

        ActivityLog::record('update_status:paid', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم تحصيل الفاتورة.'));
    }

    public function markOverdue(): void
    {
        $this->authorize('updateStatus', $this->invoice);

        if ($this->invoice->status !== 'sent') {
            Flux::toast(variant: 'danger', text: __('لا يمكن تعليم الفاتورة كمتأخرة في حالتها الحالية.'));

            return;
        }

        $this->invoice->update(['status' => 'overdue']);

        ActivityLog::record('update_status:overdue', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم تعليم الفاتورة كمتأخرة.'));
    }

    public function markCancelled(): void    {
        $this->authorize('updateStatus', $this->invoice);

        if (! in_array($this->invoice->status, ['draft', 'sent', 'overdue'], true)) {
            Flux::toast(variant: 'danger', text: __('لا يمكن إلغاء الفاتورة في حالتها الحالية.'));

            return;
        }

        $this->invoice->update(['status' => 'cancelled']);

        ActivityLog::record('update_status:cancelled', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم إلغاء الفاتورة.'));
    }

    public function sendEmail(): void
    {
        $this->authorize('updateStatus', $this->invoice);

        $email = $this->invoice->client?->email;

        if (! $email) {
            Flux::toast(variant: 'danger', text: __('لا يوجد بريد إلكتروني مسجل لهذا العميل.'));

            return;
        }

        Mail::to($email, $this->invoice->client->company_name ?? $this->invoice->client->name)
            ->send(new InvoiceMailable($this->invoice));

        ActivityLog::record('emailed', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم إرسال الفاتورة بالبريد الإلكتروني.'));
    }

    public function addItem(): void
    {
        $this->authorize('update', $this->invoice);

        if (in_array($this->invoice->status, ['paid', 'cancelled'], true)) {
            Flux::toast(variant: 'danger', text: __('لا يمكن تعديل بنود فاتورة مدفوعة أو ملغاة.'));

            return;
        }

        $this->validate([
            'itemDescription' => ['required', 'string', 'max:255'],
            'itemQuantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'itemUnitPrice' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $this->invoice->items()->create([
            'description' => $this->itemDescription,
            'quantity' => $this->itemQuantity,
            'unit_price' => $this->itemUnitPrice,
        ]);

        ActivityLog::record('added_item', $this->invoice);

        Flux::toast(variant: 'success', text: __('تمت إضافة البند وإعادة حساب الإجمالي.'));

        $this->reset('itemDescription', 'itemQuantity', 'itemUnitPrice');
        $this->itemQuantity = 1;
        $this->invoice->refresh(['client', 'workOrder', 'items']);
    }

    public function deleteItem(InvoiceItem $item): void
    {
        $this->authorize('update', $this->invoice);

        abort_unless($item->invoice_id === $this->invoice->id, 404);

        if (in_array($this->invoice->status, ['paid', 'cancelled'], true)) {
            Flux::toast(variant: 'danger', text: __('لا يمكن تعديل بنود فاتورة مدفوعة أو ملغاة.'));

            return;
        }

        $item->delete();

        ActivityLog::record('deleted_item', $this->invoice);

        Flux::toast(variant: 'success', text: __('تم حذف البند.'));

        $this->invoice->refresh(['client', 'workOrder', 'items']);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-right" :href="route('invoices.index')" wire:navigate aria-label="{{ __('رجوع') }}" />
            <div>
                <flux:heading class="font-mono">{{ $invoice->invoice_number }}</flux:heading>
                <flux:subheading>{{ $invoice->client->company_name ?? $invoice->client->name }}</flux:subheading>
            </div>
        </div>
        <flux:badge size="lg" :color="$this->statuses[$invoice->status]['color']">
            {{ $this->statuses[$invoice->status]['label'] }}
        </flux:badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <flux:card>
                <flux:heading size="lg">{{ __('بيانات الفاتورة') }}</flux:heading>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('العميل') }}</span>
                        <span class="font-medium">{{ $invoice->client->company_name ?? $invoice->client->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('أمر الشغل') }}</span>
                        <span class="font-mono">{{ $invoice->workOrder?->code ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('تاريخ الإصدار') }}</span>
                        <span>{{ $invoice->issue_date?->translatedFormat('d M Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('تاريخ الاستحقاق') }}</span>
                        <span>{{ $invoice->due_date?->translatedFormat('d M Y') ?? '—' }}</span>
                    </div>
                    @if ($invoice->paid_at)
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">{{ __('تاريخ التحصيل') }}</span>
                            <span>{{ $invoice->paid_at->translatedFormat('d M Y H:i') }}</span>
                        </div>
                    @endif
                    <flux:separator class="my-2" />
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('المجموع الفرعي') }}</span>
                        <span>{{ number_format((float) $invoice->subtotal, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('الضريبة') }}</span>
                        <span>{{ number_format((float) $invoice->tax, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-base font-bold">
                        <span>{{ __('الإجمالي') }}</span>
                        <span>{{ number_format((float) $invoice->total_amount, 2) }}</span>
                    </div>
                </div>

                @if (! in_array($invoice->status, ['paid', 'cancelled']))
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (in_array($invoice->status, ['draft', 'overdue']))
                            <flux:button variant="primary" size="sm" wire:click="markSent">{{ __('إرسال') }}</flux:button>
                        @endif
                        <flux:button variant="primary" size="sm" wire:click="markPaid">{{ __('تحصيل') }}</flux:button>
                        @if ($invoice->status === 'sent')
                            <flux:button variant="ghost" size="sm" wire:click="markOverdue">{{ __('تعليم كمتأخرة') }}</flux:button>
                        @endif
                        <flux:button variant="danger" size="sm" wire:click="markCancelled">{{ __('إلغاء') }}</flux:button>
                    </div>
                @endif

                @if (in_array($invoice->status, ['sent', 'overdue', 'paid']))
                    <div class="mt-2 flex flex-wrap gap-2">
                        <flux:button variant="ghost" size="sm" icon="envelope" wire:click="sendEmail">{{ __('إرسال بالبريد') }}</flux:button>
                        <a href="{{ route('invoices.print', $invoice) }}" target="_blank">
                            <flux:button variant="ghost" size="sm" icon="printer">{{ __('طباعة / PDF') }}</flux:button>
                        </a>
                    </div>
                @endif
            </flux:card>

            @if ($invoice->notes)
                <flux:card>
                    <flux:heading size="lg">{{ __('ملاحظات') }}</flux:heading>
                    <flux:text class="mt-2">{{ $invoice->notes }}</flux:text>
                </flux:card>
            @endif
        </div>

        <div class="lg:col-span-2">
            <flux:card>
                <flux:heading size="lg">{{ __('بنود الفاتورة') }}</flux:heading>

                @if (! in_array($invoice->status, ['paid', 'cancelled']))
                    <div class="mt-4 grid gap-3 md:grid-cols-[1fr_8rem_9rem_auto] md:items-end">
                        <flux:field>
                            <flux:label>{{ __('الوصف') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="itemDescription" placeholder="{{ __('مثال: صيانة وحدة تكييف') }}" />
                            <flux:error name="itemDescription" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('الكمية') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input type="number" step="0.01" min="0.01" wire:model="itemQuantity" />
                            <flux:error name="itemQuantity" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('سعر الوحدة') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input type="number" step="0.01" min="0" wire:model="itemUnitPrice" />
                            <flux:error name="itemUnitPrice" />
                        </flux:field>
                        <flux:button variant="primary" icon="plus" wire:click="addItem">{{ __('إضافة') }}</flux:button>
                    </div>
                @endif

                <div class="mt-4 overflow-hidden">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('الوصف') }}</flux:table.column>
                            <flux:table.column>{{ __('الكمية') }}</flux:table.column>
                            <flux:table.column>{{ __('سعر الوحدة') }}</flux:table.column>
                            <flux:table.column>{{ __('الإجمالي') }}</flux:table.column>
                            @if (! in_array($invoice->status, ['paid', 'cancelled']))
                                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
                            @endif
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($invoice->items as $item)
                                <flux:table.row :key="$item->id" wire:key="item-{{ $item->id }}">
                                    <flux:table.cell>{{ $item->description }}</flux:table.cell>
                                    <flux:table.cell>{{ $item->quantity }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format((float) $item->unit_price, 2) }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format((float) $item->total, 2) }}</flux:table.cell>
                                    @if (! in_array($invoice->status, ['paid', 'cancelled']))
                                        <flux:table.cell>
                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="deleteItem({{ $item->id }})" />
                                            </div>
                                        </flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="!text-center">
                                        <div class="py-8 text-center">
                                            <flux:text>{{ __('لا توجد بنود بعد. أضف أول بند بالأعلى.') }}</flux:text>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>
    </div>
</div>
