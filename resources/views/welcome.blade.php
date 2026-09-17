<x-layouts::auth :title="__('نظام إدارة الصيانة')">
    <div class="flex flex-col gap-6 text-center">
        <div class="flex justify-center">
            <span class="flex size-14 items-center justify-center rounded-xl bg-accent-content text-accent-foreground">
                <x-app-logo-icon class="size-8 fill-current text-white dark:text-black" />
            </span>
        </div>

        <div>
            <flux:heading size="xl" level="1">{{ __('نظام إدارة الصيانة') }}</flux:heading>
            <flux:subheading class="mt-2">
                {{ __('إدارة الأصول والمعدات وأوامر الشغل والصيانة الوقائية وقطع الغيار في مكان واحد') }}
            </flux:subheading>
        </div>

        @auth
            <div class="grid gap-3">
                <flux:button variant="primary" href="{{ route('dashboard') }}" class="w-full">
                    {{ __('لوحة التحكم') }}
                </flux:button>
            </div>
        @endauth

        @guest
            <div class="grid gap-3">
                <flux:button variant="primary" href="{{ route('login') }}" class="w-full">
                    {{ __('تسجيل الدخول') }}
                </flux:button>

                @if (Route::has('register'))
                    <flux:button href="{{ route('register') }}" class="w-full">
                        {{ __('إنشاء حساب جديد') }}
                    </flux:button>
                @endif
            </div>
        @endguest

        <flux:separator />

        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <flux:heading size="lg">0</flux:heading>
                <flux:text class="mt-1">{{ __('أوامر شغل') }}</flux:text>
            </div>
            <div>
                <flux:heading size="lg">0</flux:heading>
                <flux:text class="mt-1">{{ __('أصل ومعدة') }}</flux:text>
            </div>
            <div>
                <flux:heading size="lg">0</flux:heading>
                <flux:text class="mt-1">{{ __('قطعة غيار') }}</flux:text>
            </div>
        </div>
    </div>
</x-layouts::auth>