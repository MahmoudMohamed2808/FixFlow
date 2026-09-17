<?php

namespace App\Notifications;

use App\Models\PreventiveSchedule;
use App\Models\User;

class ScheduleNotification extends AppNotification
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const TOGGLED = 'toggled';

    public const DUE_SOON = 'due_soon';

    public const OVERDUE = 'overdue';

    public const WORK_ORDER_GENERATED = 'work_order_generated';

    public function __construct(
        public PreventiveSchedule $schedule,
        public string $event = self::CREATED,
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return 'schedule.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        return in_array($this->event, [self::OVERDUE, self::WORK_ORDER_GENERATED], true);
    }

    public function title(): string
    {
        return match ($this->event) {
            self::TOGGLED => __('تم :state الجدول الوقائي :title', [
                'state' => ($this->meta['is_active'] ?? $this->schedule->is_active) ? __('تفعيل') : __('إيقاف'),
                'title' => $this->schedule->title,
            ]),
            self::DELETED => __('تم حذف الجدول الوقائي :title', ['title' => $this->schedule->title]),
            self::UPDATED => __('تم تحديث الجدول الوقائي :title', ['title' => $this->schedule->title]),
            self::DUE_SOON => __('موعد صيانة وقائية قريب: :title', ['title' => $this->schedule->title]),
            self::OVERDUE => __('جدول صيانة متأخر: :title', ['title' => $this->schedule->title]),
            self::WORK_ORDER_GENERATED => __('تم توليد أمر شغل وقائي: :title', ['title' => $this->schedule->title]),
            default => __('جدول وقائي جديد: :title', ['title' => $this->schedule->title]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');
        $asset = $this->schedule->asset?->name ?? '—';
        $due = $this->schedule->next_due_date?->format('Y-m-d') ?? '—';

        return __('بواسطة :actor — الأصل: :asset — الاستحقاق: :due', [
            'actor' => $actor,
            'asset' => $asset,
            'due' => $due,
        ]);
    }

    public function url(): string
    {
        return route('schedules.index');
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->schedule->getMorphClass(),
            'model_id' => $this->schedule->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }
}
