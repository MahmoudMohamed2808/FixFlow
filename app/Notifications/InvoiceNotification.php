<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;

class InvoiceNotification extends AppNotification
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const STATUS_CHANGED = 'status_changed';

    public const EMAILED = 'emailed';

    public const ITEM_ADDED = 'item_added';

    public const ITEM_DELETED = 'item_deleted';

    public const OVERDUE = 'overdue';

    public function __construct(
        public Invoice $invoice,
        public string $event = self::CREATED,
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return 'invoice.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        if ($this->event === self::STATUS_CHANGED) {
            return in_array($this->meta['status'] ?? $this->invoice->status, ['sent', 'paid', 'overdue', 'cancelled'], true);
        }

        return in_array($this->event, [self::OVERDUE, self::EMAILED], true);
    }

    public function title(): string
    {
        $number = $this->invoice->invoice_number;

        return match ($this->event) {
            self::STATUS_CHANGED => __('تغيّرت حالة الفاتورة :number إلى :status', [
                'number' => $number,
                'status' => $this->meta['status_label'] ?? ($this->meta['status'] ?? $this->invoice->status),
            ]),
            self::DELETED => __('تم حذف الفاتورة :number', ['number' => $number]),
            self::EMAILED => __('تم إرسال الفاتورة :number بالبريد', ['number' => $number]),
            self::ITEM_ADDED => __('بند جديد في الفاتورة :number', ['number' => $number]),
            self::ITEM_DELETED => __('تم حذف بند من الفاتورة :number', ['number' => $number]),
            self::OVERDUE => __('الفاتورة :number متأخرة', ['number' => $number]),
            self::UPDATED => __('تم تحديث الفاتورة :number', ['number' => $number]),
            default => __('فاتورة جديدة :number', ['number' => $number]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');
        $total = number_format((float) $this->invoice->total_amount, 2);
        $client = $this->invoice->client?->company_name ?? $this->invoice->client?->name ?? '—';

        return __('بواسطة :actor — العميل: :client — الإجمالي: :total', [
            'actor' => $actor,
            'client' => $client,
            'total' => $total,
        ]);
    }

    public function url(): string
    {
        if ($this->event === self::DELETED) {
            return route('invoices.index');
        }

        return route('invoices.show', $this->invoice);
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->invoice->getMorphClass(),
            'model_id' => $this->invoice->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }
}
