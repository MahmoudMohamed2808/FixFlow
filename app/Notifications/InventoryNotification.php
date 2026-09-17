<?php

namespace App\Notifications;

use App\Models\SparePart;
use App\Models\User;

class InventoryNotification extends AppNotification
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const STOCK_ADJUSTED = 'stock_adjusted';

    public const LOW_STOCK = 'low_stock';

    public const BACK_IN_STOCK = 'back_in_stock';

    public function __construct(
        public SparePart $part,
        public string $event = self::CREATED,
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return 'inventory.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        return $this->event === self::LOW_STOCK;
    }

    public function title(): string
    {
        return match ($this->event) {
            self::LOW_STOCK => __('تنبيه مخزون: :name وصل للحد الأدنى', ['name' => $this->part->name]),
            self::BACK_IN_STOCK => __('عاد التوفر: :name', ['name' => $this->part->name]),
            self::STOCK_ADJUSTED => __('تم تعديل مخزون :name', ['name' => $this->part->name]),
            self::DELETED => __('تم حذف القطعة :name', ['name' => $this->part->name]),
            self::UPDATED => __('تم تحديث القطعة :name', ['name' => $this->part->name]),
            default => __('قطعة جديدة: :name', ['name' => $this->part->name]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');

        return __('بواسطة :actor — :name (:code) — المتاح: :stock / الحد: :min', [
            'actor' => $actor,
            'name' => $this->part->name,
            'code' => $this->part->code,
            'stock' => (string) $this->part->quantity_in_stock,
            'min' => (string) $this->part->minimum_quantity,
        ]);
    }

    public function url(): string
    {
        return route('inventory.index');
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->part->getMorphClass(),
            'model_id' => $this->part->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }
}
