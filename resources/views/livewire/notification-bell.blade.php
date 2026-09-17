<div wire:poll.30s class="relative">
    <flux:dropdown position="bottom" align="end">
        <flux:tooltip :content="__('الإشعارات')" position="bottom">
            <flux:button
                variant="ghost"
                icon="bell"
                :label="__('الإشعارات')"
                data-test="notification-bell"
                class="relative! [&>div>svg]:size-5"
            />
        </flux:tooltip>

        @if ($this->unreadCount > 0)
            <span
                class="pointer-events-none absolute -top-0.5 end-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white"
            >
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif

        <flux:menu class="min-w-80 max-w-sm">
            <div class="flex items-center justify-between px-2 py-1.5">
                <flux:heading size="sm">{{ __('الإشعارات') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:button variant="ghost" size="sm" wire:click="markAllRead">
                        {{ __('تعليم الكل كمقروء') }}
                    </flux:button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($this->latest as $notification)
                <flux:menu.item
                    :href="route('notifications.index')"
                    wire:navigate
                    class="{{ $notification->read_at === null ? 'bg-accent/5 font-medium' : '' }}"
                >
                    <div class="flex w-full items-start gap-2">
                        @if ($notification->read_at === null)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-accent"></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm">{{ $notification->data['title'] ?? __('إشعار جديد') }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $notification->data['body'] ?? '' }}</div>
                            <div class="mt-0.5 text-[11px] text-zinc-400">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-4 py-6 text-center">
                    <flux:text>{{ __('لا توجد إشعارات بعد.') }}</flux:text>
                </div>
            @endforelse

            <flux:menu.separator />

            <flux:menu.item :href="route('notifications.index')" icon="eye" wire:navigate>
                {{ __('عرض كل الإشعارات') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
