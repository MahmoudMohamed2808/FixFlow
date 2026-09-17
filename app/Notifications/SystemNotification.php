<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends AppNotification
{
    public function __construct(
        public Model $subject,
        public string $event = 'created',
        public string $domain = 'system',
        public ?User $actor = null,
        public array $meta = []
    ) {}

    public function action(): string
    {
        return $this->domain.'.'.$this->event;
    }

    protected function wantsMail(): bool
    {
        return in_array($this->action(), [
            'budget.exceeded',
            'budget.threshold',
            'user.created',
            'user.deactivated',
        ], true);
    }

    public function title(): string
    {
        $name = $this->displayName();

        return match ($this->action()) {
            'client.created' => __('عميل جديد: :name', ['name' => $name]),
            'client.updated' => __('تم تحديث العميل :name', ['name' => $name]),
            'client.deleted' => __('تم حذف العميل :name', ['name' => $name]),
            'supplier.created' => __('مورد جديد: :name', ['name' => $name]),
            'supplier.updated' => __('تم تحديث المورد :name', ['name' => $name]),
            'supplier.deleted' => __('تم حذف المورد :name', ['name' => $name]),
            'budget.created' => __('ميزانية جديدة: :name', ['name' => $name]),
            'budget.updated' => __('تم تحديث الميزانية :name', ['name' => $name]),
            'budget.deleted' => __('تم حذف الميزانية :name', ['name' => $name]),
            'budget.threshold' => __('الميزانية :name تجاوزت :percent%', [
                'name' => $name,
                'percent' => (string) ($this->meta['percent'] ?? 80),
            ]),
            'budget.exceeded' => __('تجاوز الميزانية :name', ['name' => $name]),
            'user.created' => __('مستخدم جديد: :name', ['name' => $name]),
            'user.updated' => __('تم تحديث المستخدم :name', ['name' => $name]),
            'user.deactivated' => __('تم إيقاف المستخدم :name', ['name' => $name]),
            'user.activated' => __('تم تفعيل المستخدم :name', ['name' => $name]),
            'user.role_changed' => __('تم تغيير دور المستخدم :name', ['name' => $name]),
            'user.deleted' => __('تم حذف المستخدم :name', ['name' => $name]),
            default => __('تحديث نظام: :name', ['name' => $name]),
        };
    }

    public function body(): string
    {
        $actor = $this->actor?->name ?? __('النظام');

        return __('بواسطة :actor', ['actor' => $actor]);
    }

    public function url(): string
    {
        return match ($this->domain) {
            'client' => route('clients.index'),
            'supplier' => route('suppliers.index'),
            'budget' => route('budgets.index'),
            'user' => route('users.index'),
            default => route('dashboard'),
        };
    }

    protected function payload(): array
    {
        return [
            'model_type' => $this->subject->getMorphClass(),
            'model_id' => $this->subject->getKey(),
            'actor_id' => $this->actor?->getKey(),
            'actor_name' => $this->actor?->name,
            'meta' => $this->meta,
        ];
    }

    private function displayName(): string
    {
        foreach (['title', 'name', 'company_name', 'email'] as $key) {
            $value = $this->subject->getAttribute($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$this->subject->getKey();
    }
}
