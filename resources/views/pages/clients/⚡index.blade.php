<?php

use App\Models\ActivityLog;
use App\Models\Client;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('العملاء')] class extends Component {
    public string $search = '';

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $clientId = null;

    public ?int $deletingClientId = null;

    public string $name = '';

    public ?string $companyName = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public ?string $notes = null;

    #[Computed]
    public function clients()
    {
        return Client::query()
            ->withCount(['workOrders', 'invoices'])
            ->withSum(['invoices as outstanding_sum' => fn ($query) => $query->whereIn('status', ['sent', 'overdue'])], 'total_amount')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('company_name', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Client::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(Client $client): void
    {
        $this->authorize('update', $client);

        $this->clientId = $client->id;
        $this->name = $client->name;
        $this->companyName = $client->company_name;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->address = $client->address;
        $this->notes = $client->notes;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'clientId', 'name', 'companyName', 'phone', 'email', 'address', 'notes',
        ]);
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->clientId === null) {
            $this->authorize('create', Client::class);
        } else {
            $this->authorize('update', Client::findOrFail($this->clientId));
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'companyName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'name' => $validated['name'],
            'company_name' => $validated['companyName'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->clientId === null) {
            $client = Client::create($data);
            ActivityLog::record('created', $client);
            Flux::toast(variant: 'success', text: __('تم إنشاء العميل بنجاح.'));
        } else {
            $client = Client::findOrFail($this->clientId);
            $client->update($data);
            ActivityLog::record('updated', $client);
            Flux::toast(variant: 'success', text: __('تم تحديث بيانات العميل.'));
        }

        $this->closeForm();
    }

    public function askDelete(Client $client): void
    {
        $this->authorize('delete', $client);

        $this->deletingClientId = $client->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $client = Client::findOrFail($this->deletingClientId);

        $this->authorize('delete', $client);

        if ($client->invoices()->exists()) {
            Flux::toast(variant: 'danger', text: __('لا يمكن حذف عميل لديه فواتير مسجلة.'));

            $this->confirmingDelete = false;
            $this->deletingClientId = null;

            return;
        }

        $client->delete();

        ActivityLog::record('deleted', $client);

        Flux::toast(variant: 'success', text: __('تم حذف العميل.'));

        $this->confirmingDelete = false;
        $this->deletingClientId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('العملاء') }}</flux:heading>
            <flux:subheading>{{ __('إدارة عملاء الشركة وأرصدتهم') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إضافة عميل') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالاسم أو الشركة أو الهاتف...') }}" />
        </flux:input.group>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->clients">
            <flux:table.columns>
                <flux:table.column>{{ __('العميل') }}</flux:table.column>
                <flux:table.column>{{ __('التواصل') }}</flux:table.column>
                <flux:table.column>{{ __('أوامر الشغل') }}</flux:table.column>
                <flux:table.column>{{ __('الفواتير') }}</flux:table.column>
                <flux:table.column>{{ __('المستحق') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->clients as $client)
                    <flux:table.row :key="$client->id" wire:key="client-{{ $client->id }}">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $client->name }}</div>
                            @if ($client->company_name)
                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $client->company_name }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($client->phone)
                                <div class="text-sm" dir="ltr">{{ $client->phone }}</div>
                            @endif
                            @if ($client->email)
                                <div class="text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $client->email }}</div>
                            @endif
                            @if (! $client->phone && ! $client->email)
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $client->work_orders_count }}</flux:table.cell>
                        <flux:table.cell>{{ $client->invoices_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ((float) $client->outstanding_sum > 0)
                                <flux:badge size="sm" color="amber">{{ number_format((float) $client->outstanding_sum, 2) }}</flux:badge>
                            @else
                                <span>—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $client->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $client->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا يوجد عملاء') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإضافة أول عميل.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $clientId === null ? __('إضافة عميل جديد') : __('تعديل العميل') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات العميل بالأسفل، الحقول المميزة بـ * مطلوبة.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('اسم العميل') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="{{ __('مثال: محمد علي') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('اسم الشركة') }}</flux:label>
                <flux:input wire:model="companyName" placeholder="{{ __('مثال: شركة النور') }}" />
                <flux:error name="companyName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الهاتف') }}</flux:label>
                <flux:input wire:model="phone" placeholder="{{ __('01xxxxxxxxx') }}" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }}</flux:label>
                <flux:input wire:model="email" type="email" placeholder="{{ __('client@example.com') }}" />
                <flux:error name="email" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('العنوان') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('ملاحظات') }}</flux:label>
                <flux:textarea wire:model="notes" rows="3" />
                <flux:error name="notes" />
            </flux:field>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="closeForm">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="save">{{ __('حفظ') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="confirmingDelete" class="min-w-sm">
        <flux:heading size="lg">{{ __('حذف العميل') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا العميل؟ لا يمكن حذف عميل لديه فواتير مسجلة.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>
