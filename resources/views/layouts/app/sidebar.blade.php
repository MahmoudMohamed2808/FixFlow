<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('الرئيسية')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('لوحة التحكم') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>
                        {{ __('الإشعارات') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('العمليات')" class="grid">
                    @can('assets.view')
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('assets.index')" :current="request()->routeIs('assets.*')" wire:navigate>
                            {{ __('الأصول والمعدات') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('work_orders.view')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('work-orders.index')" :current="request()->routeIs('work-orders.*')" wire:navigate>
                            {{ __('أوامر الشغل') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('schedules.view')
                        <flux:sidebar.item icon="calendar-days" :href="route('schedules.index')" :current="request()->routeIs('schedules.*')" wire:navigate>
                            {{ __('الصيانة الوقائية') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('المخازن')" class="grid">
                    @can('inventory.view')
                        <flux:sidebar.item icon="archive-box" :href="route('inventory.index')" :current="request()->routeIs('inventory.*')" wire:navigate>
                            {{ __('قطع الغيار') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('suppliers.view')
                        <flux:sidebar.item icon="truck" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                            {{ __('الموردون') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('التقارير')" class="grid">
                    @can('reports.view')
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                            {{ __('التقارير') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('المالية')" class="grid">
                    @can('clients.view')
                        <flux:sidebar.item icon="building-office-2" :href="route('clients.index')" :current="request()->routeIs('clients.*')" wire:navigate>
                            {{ __('العملاء') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('invoices.view')
                        <flux:sidebar.item icon="document-text" :href="route('invoices.index')" :current="request()->routeIs('invoices.*')" wire:navigate>
                            {{ __('الفواتير') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('budgets.view')
                        <flux:sidebar.item icon="banknotes" :href="route('budgets.index')" :current="request()->routeIs('budgets.*')" wire:navigate>
                            {{ __('الميزانيات') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                @can('users.manage')
                    <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                        {{ __('المستخدمون') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('activity-logs.index')" :current="request()->routeIs('activity-logs.*')" wire:navigate>
                        {{ __('سجل العمليات') }}
                    </flux:sidebar.item>
                @endcan

                <flux:sidebar.group :heading="__('الإعدادات')" class="grid">
                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'appearance.edit', 'security.edit')" wire:navigate>
                        {{ __('الإعدادات') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />

            <div class="hidden items-center justify-center border-t border-zinc-200 py-2 lg:flex dark:border-zinc-700">
                <livewire:notification-bell />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('الإعدادات') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('تسجيل الخروج') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
