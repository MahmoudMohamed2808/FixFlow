<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الإشعارات')] class extends Component {
    public bool $unreadOnly = false;

    #[Computed]
    public function notifications()
    {
        return auth()->user()
            ->notifications()
            ->when($this->unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function toggleFilter(): void
    {
        $this->unreadOnly = ! $this->unreadOnly;
        unset($this->notifications);
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = $notification->data['url'] ?? route('notifications.index');

        $this->redirect($url, navigate: true);
    }

    public function markAsRead(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();

        if ($notification !== null && $notification->read_at === null) {
            $notification->markAsRead();
        }

        unset($this->notifications, $this->unreadCount);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6" wire:poll.30s>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الإشعارات') }}</flux:heading>
            <flux:subheading>
                @if ($this->unreadCount > 0)
                    {{ __('لديك :count إشعارات غير مقروءة', ['count' => $this->unreadCount]) }}
                @else
                    {{ __('كل الإشعارات مقروءة') }}
                @endif
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" wire:click="toggleFilter">
                {{ $unreadOnly ? __('عرض الكل') : __('غير المقروءة فقط') }}
            </flux:button>
            @if ($this->unreadCount > 0)
                <flux:button variant="primary" wire:click="markAllRead">
                    {{ __('تعليم الكل كمقروء') }}
                </flux:button>
            @endif
        </div>
    </div>

    <flux:card class="overflow-hidden">
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->notifications as $notification)
                <div
                    wire:key="notification-{{ $notification->id }}"
                    class="flex items-start gap-3 p-4 {{ $notification->read_at === null ? 'bg-accent/5' : '' }}"
                >
                    @if ($notification->read_at === null)
                        <span class="mt-2 size-2 shrink-0 rounded-full bg-accent"></span>
                    @endif
                    <button wire:click="open('{{ $notification->id }}')" class="min-w-0 flex-1 cursor-pointer text-start">
                        <div class="text-sm font-medium text-zinc-800 dark:text-white">
                            {{ $notification->data['title'] ?? __('إشعار جديد') }}
                        </div>
                        <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $notification->data['body'] ?? '' }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-400">
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </button>
                    @if ($notification->read_at === null)
                        <flux:button variant="ghost" size="sm" wire:click="markAsRead('{{ $notification->id }}')">
                            {{ __('مقروء') }}
                        </flux:button>
                    @endif
                </div>
            @empty
                <div class="py-10 text-center">
                    <flux:heading size="lg">{{ __('لا توجد إشعارات') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('ستظهر هنا التنبيهات عن أوامر الشغل والفواتير والمخزون.') }}</flux:text>
                </div>
            @endforelse
        </div>

        @if ($this->notifications->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->notifications->links() }}
            </div>
        @endif
    </flux:card>
</div>
