<?php

use App\Models\Asset;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('تفاصيل الأصل')] class extends Component {
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load(['category', 'location', 'creator']);
    }

    #[Computed]
    public function workOrders()
    {
        return $this->asset->workOrders()
            ->with(['assignedTo', 'reportedBy'])
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function statusInfo(): array
    {
        return match ($this->asset->status) {
            'under_maintenance' => ['label' => __('قيد الصيانة'), 'color' => 'info'],
            'broken' => ['label' => __('معطوب'), 'color' => 'danger'],
            'retired' => ['label' => __('خارج الخدمة'), 'color' => 'secondary'],
            default => ['label' => __('تشغيلي'), 'color' => 'success'],
        };
    }

    #[Computed]
    public function workOrderStatuses(): array
    {
        return [
            'open' => ['label' => __('مفتوحة'), 'color' => 'warning'],
            'assigned' => ['label' => __('تم التعيين'), 'color' => 'info'],
            'in_progress' => ['label' => __('قيد التنفيذ'), 'color' => 'info'],
            'completed' => ['label' => __('مكتملة'), 'color' => 'success'],
            'cancelled' => ['label' => __('ملغاة'), 'color' => 'danger'],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-right" :href="route('assets.index')" wire:navigate aria-label="{{ __('رجوع') }}" />
            <div>
                <flux:heading>{{ $this->asset->name }}</flux:heading>
                <flux:subheading>
                    {{ __('الرقم التسلسلي') }}: <span class="font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $this->asset->serial_number }}</span>
                </flux:subheading>
            </div>
        </div>
        <flux:badge size="lg" :color="$this->statusInfo['color']">{{ $this->statusInfo['label'] }}</flux:badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <flux:card>
                <div class="space-y-4">
                    @if ($this->asset->image)
                        <img src="{{ asset('storage/'.$this->asset->image) }}" alt="{{ $this->asset->name }}" class="h-48 w-full rounded-lg object-cover">
                    @else
                        <div class="flex h-48 w-full items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon icon="wrench-screwdriver" variant="solid" class="size-12 text-zinc-400" />
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <flux:text size="xs">{{ __('التصنيف') }}</flux:text>
                            <flux:text>{{ $this->asset->category?->name ?? '—' }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="xs">{{ __('الموقع') }}</flux:text>
                            <flux:text>{{ $this->asset->location?->name ?? '—' }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="xs">{{ __('الشركة المصنعة') }}</flux:text>
                            <flux:text>{{ $this->asset->manufacturer ?? '—' }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="xs">{{ __('الطراز') }}</flux:text>
                            <flux:text>{{ $this->asset->model ?? '—' }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="xs">{{ __('تاريخ الشراء') }}</flux:text>
                            <flux:text>{{ $this->asset->purchase_date?->translatedFormat('d M Y') ?? '—' }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="xs">{{ __('انتهاء الضمان') }}</flux:text>
                            <flux:text>{{ $this->asset->warranty_expiry?->translatedFormat('d M Y') ?? '—' }}</flux:text>
                        </div>
                        <div class="col-span-2">
                            <flux:text size="xs">{{ __('أُضيف بواسطة') }}</flux:text>
                            <flux:text>{{ $this->asset->creator?->name ?? '—' }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <flux:card>
                <flux:heading size="lg">{{ __('تاريخ الصيانة') }}</flux:heading>
                <flux:separator class="mt-3" />

                <div class="mt-4 space-y-3">
                    @forelse ($this->workOrders as $order)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                <div>
                                    <flux:text class="font-medium">{{ $order->code }}</flux:text>
                                    <flux:text size="sm" class="mt-1">{{ $order->description }}</flux:text>
                                </div>
                            </div>
                            <div class="text-end">
                                <flux:badge size="sm" :color="$this->workOrderStatuses[$order->status]['color']">
                                    {{ $this->workOrderStatuses[$order->status]['label'] }}
                                </flux:badge>
                                @if ($order->scheduled_date)
                                    <div class="mt-1">
                                        <flux:text size="xs">{{ $order->scheduled_date->translatedFormat('d M Y') }}</flux:text>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            <flux:heading size="lg">{{ __('لا توجد أوامر شغل لهذا الأصل') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('ستظهر أوامر الشغل هنا بعد إنشائها من وحدة أوامر الشغل.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </div>
</div>