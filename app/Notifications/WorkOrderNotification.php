<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderNotification extends AppNotification
{
    public const CREATED = 'created';

    public const ASSIGNED = 'assigned';

    public const STATUS_CHANGED = 'status_changed';

    public const COMMENTED = 'commented';

    public const PART_ADDED = 'part_added';

    public const LABOR_ADDED = 'labor_added';

    public const LABOR_DELETED = 'labor_deleted';

    public const ATTACHMENT_UPLOADED = 'attachment_uploaded';

    public const DELETED = 'deleted';

    public const PREVENTIVE_GENERATED = 'preventive_generated';

    /** @var array<int, string> */
    private const MAIL_ACTIONS = [
        self::ASSIGNED,
        self::PREVENTIVE_GENERATED,
    ];

    public function __construct(
        public WorkOrder $workOrder,
        public string $event = self::CREATED,
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return 'work_order.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        if (in_array($this->event, self::MAIL_ACTIONS, true)) {
            return true;
        }

        if ($this->event === self::STATUS_CHANGED) {
            return in_array($this->meta['status'] ?? null, ['completed', 'cancelled'], true);
        }

        return false;
    }

    public function title(): string
    {
        return match ($this->event) {
            self::ASSIGNED => __('تم تعيينك على أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::STATUS_CHANGED => __('تغيّرت حالة أمر الشغل :code إلى :status', [
                'code' => $this->workOrder->code,
                'status' => $this->meta['status_label'] ?? ($this->meta['status'] ?? ''),
            ]),
            self::COMMENTED => __('تعليق جديد على أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::PART_ADDED => __('تمت إضافة قطعة غيار لأمر الشغل :code', ['code' => $this->workOrder->code]),
            self::LABOR_ADDED => __('تم تسجيل ساعات عمل على أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::LABOR_DELETED => __('تم حذف قيد ساعات من أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::ATTACHMENT_UPLOADED => __('مرفق جديد على أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::DELETED => __('تم حذف أمر الشغل :code', ['code' => $this->workOrder->code]),
            self::PREVENTIVE_GENERATED => __('أمر شغل وقائي جديد :code', ['code' => $this->workOrder->code]),
            default => __('أمر شغل جديد :code', ['code' => $this->workOrder->code]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');

        return match ($this->event) {
            self::ASSIGNED => __('قام :actor بتعيينك على الأمر (:asset).', [
                'actor' => $actor,
                'asset' => $this->workOrder->asset?->name ?? '—',
            ]),
            self::STATUS_CHANGED => __('قام :actor بتحديث الحالة. الوصف: :description', [
                'actor' => $actor,
                'description' => $this->workOrder->description ?? '—',
            ]),
            self::COMMENTED => __('علّق :actor: :comment', [
                'actor' => $actor,
                'comment' => (string) ($this->meta['comment'] ?? ''),
            ]),
            self::PART_ADDED => __('أضاف :actor القطعة :part (x:qty).', [
                'actor' => $actor,
                'part' => (string) ($this->meta['part_name'] ?? ''),
                'qty' => (string) ($this->meta['quantity'] ?? ''),
            ]),
            self::LABOR_ADDED => __('سجّل :actor :hours ساعة للفني :tech.', [
                'actor' => $actor,
                'hours' => (string) ($this->meta['hours'] ?? ''),
                'tech' => (string) ($this->meta['technician_name'] ?? ''),
            ]),
            self::DELETED => __('حذف :actor أمر الشغل (:description).', [
                'actor' => $actor,
                'description' => $this->workOrder->description ?? '',
            ]),
            default => __('بواسطة :actor — :description', [
                'actor' => $actor,
                'description' => $this->workOrder->description ?? '',
            ]),
        };
    }

    public function url(): string
    {
        if ($this->event === self::DELETED) {
            return route('work-orders.index');
        }

        return route('work-orders.show', $this->workOrder);
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->workOrder->getMorphClass(),
            'model_id' => $this->workOrder->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }
}
