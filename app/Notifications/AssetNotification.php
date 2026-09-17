<?php

namespace App\Notifications;

use App\Models\Asset;
use App\Models\User;

class AssetNotification extends AppNotification
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const STATUS_CHANGED = 'status_changed';

    public const WARRANTY_EXPIRING = 'warranty_expiring';

    public const WARRANTY_EXPIRED = 'warranty_expired';

    public function __construct(
        public Asset $asset,
        public string $event = self::CREATED,
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return 'asset.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        if ($this->event === self::STATUS_CHANGED) {
            return ($this->meta['status'] ?? $this->asset->status) === 'broken';
        }

        return in_array($this->event, [self::WARRANTY_EXPIRING, self::WARRANTY_EXPIRED], true);
    }

    public function title(): string
    {
        return match ($this->event) {
            self::STATUS_CHANGED => __('تغيّرت حالة الأصل :name إلى :status', [
                'name' => $this->asset->name,
                'status' => $this->meta['status_label'] ?? ($this->meta['status'] ?? $this->asset->status),
            ]),
            self::DELETED => __('تم حذف الأصل :name', ['name' => $this->asset->name]),
            self::UPDATED => __('تم تحديث الأصل :name', ['name' => $this->asset->name]),
            self::WARRANTY_EXPIRING => __('ضمان الأصل :name يقترب من الانتهاء', ['name' => $this->asset->name]),
            self::WARRANTY_EXPIRED => __('انتهى ضمان الأصل :name', ['name' => $this->asset->name]),
            default => __('أصل جديد: :name', ['name' => $this->asset->name]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');

        return __('بواسطة :actor — تسلسلي: :serial', [
            'actor' => $actor,
            'serial' => $this->asset->serial_number ?? '—',
        ]);
    }

    public function url(): string
    {
        if ($this->event === self::DELETED) {
            return route('assets.index');
        }

        return route('assets.show', $this->asset);
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->asset->getMorphClass(),
            'model_id' => $this->asset->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }
}
