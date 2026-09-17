<?php

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Department;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الميزانيات')] class extends Component {
    public string $search = '';

    public ?int $filterDepartmentId = null;

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $budgetId = null;

    public ?int $deletingBudgetId = null;

    public string $title = '';

    public ?int $departmentId = null;

    public ?string $periodStart = null;

    public ?string $periodEnd = null;

    public ?float $allocatedAmount = null;

    public ?string $notes = null;

    #[Computed]
    public function budgets()
    {
        return Budget::query()
            ->with('department')
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->filterDepartmentId, fn ($query) => $query->where('department_id', $this->filterDepartmentId))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterDepartmentId']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Budget::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(Budget $budget): void
    {
        $this->authorize('update', $budget);

        $this->budgetId = $budget->id;
        $this->title = $budget->title;
        $this->departmentId = $budget->department_id;
        $this->periodStart = $budget->period_start?->format('Y-m-d');
        $this->periodEnd = $budget->period_end?->format('Y-m-d');
        $this->allocatedAmount = (float) $budget->allocated_amount;
        $this->notes = $budget->notes;
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
            'budgetId', 'title', 'departmentId', 'periodStart', 'periodEnd', 'allocatedAmount', 'notes',
        ]);
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->budgetId === null) {
            $this->authorize('create', Budget::class);
        } else {
            $this->authorize('update', Budget::findOrFail($this->budgetId));
        }

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'allocatedAmount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'title' => $validated['title'],
            'department_id' => $validated['departmentId'],
            'period_start' => $validated['periodStart'],
            'period_end' => $validated['periodEnd'],
            'allocated_amount' => $validated['allocatedAmount'],
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->budgetId === null) {
            $budget = Budget::create($data);
            ActivityLog::record('created', $budget);
            Flux::toast(variant: 'success', text: __('تم إنشاء الميزانية بنجاح.'));
        } else {
            $budget = Budget::findOrFail($this->budgetId);
            $budget->update($data);
            ActivityLog::record('updated', $budget);
            Flux::toast(variant: 'success', text: __('تم تحديث الميزانية.'));
        }

        $this->closeForm();
    }

    public function askDelete(Budget $budget): void
    {
        $this->authorize('delete', $budget);

        $this->deletingBudgetId = $budget->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $budget = Budget::findOrFail($this->deletingBudgetId);

        $this->authorize('delete', $budget);

        $budget->delete();

        ActivityLog::record('deleted', $budget);

        Flux::toast(variant: 'success', text: __('تم حذف الميزانية.'));

        $this->confirmingDelete = false;
        $this->deletingBudgetId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الميزانيات') }}</flux:heading>
            <flux:subheading>{{ __('متابعة ميزانيات الصيانة مقابل المصروف الفعلي') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إنشاء ميزانية') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بعنوان الميزانية...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterDepartmentId" class="md:w-52">
            <flux:select.option value="">{{ __('كل الأقسام') }}</flux:select.option>
            @foreach ($this->departments as $department)
                <flux:select.option value="{{ $department->id }}">{{ $department->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->budgets">
            <flux:table.columns>
                <flux:table.column>{{ __('الميزانية') }}</flux:table.column>
                <flux:table.column>{{ __('القسم') }}</flux:table.column>
                <flux:table.column>{{ __('الفترة') }}</flux:table.column>
                <flux:table.column>{{ __('المخصص') }}</flux:table.column>
                <flux:table.column>{{ __('المصروف / المتبقي') }}</flux:table.column>
                <flux:table.column>{{ __('الاستهلاك') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->budgets as $budget)
                    <flux:table.row :key="$budget->id" wire:key="budget-{{ $budget->id }}">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $budget->title }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $budget->department?->name ?? __('الشركة كلها') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $budget->period_start?->translatedFormat('d M Y') }}
                            <span class="text-zinc-400">→</span>
                            {{ $budget->period_end?->translatedFormat('d M Y') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $budget->allocated_amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm">{{ number_format($budget->spent_amount, 2) }}</div>
                            <div class="text-xs {{ $budget->isOverBudget() ? 'font-medium text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                {{ __('المتبقي') }}: {{ number_format($budget->remaining_amount, 2) }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full rounded-full {{ $budget->isOverBudget() ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min($budget->usage_percent, 100) }}%"></div>
                                </div>
                                <span class="text-xs">{{ $budget->usage_percent }}%</span>
                                @if ($budget->isOverBudget())
                                    <flux:badge size="sm" color="danger">{{ __('تجاوز') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $budget->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $budget->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد ميزانيات') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإنشاء أول ميزانية.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $budgetId === null ? __('إنشاء ميزانية جديدة') : __('تعديل الميزانية') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات الميزانية بالأسفل، الحقول المميزة بـ * مطلوبة.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('العنوان') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="title" placeholder="{{ __('مثال: ميزانية صيانة 2026') }}" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('القسم (اتركه فارغًا للشركة كلها)') }}</flux:label>
                <flux:select wire:model="departmentId">
                    <flux:select.option value="">{{ __('— الشركة كلها —') }}</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option value="{{ $department->id }}">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="departmentId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('بداية الفترة') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="periodStart" />
                <flux:error name="periodStart" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('نهاية الفترة') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="periodEnd" />
                <flux:error name="periodEnd" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('المبلغ المخصص') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" step="0.01" min="0" wire:model="allocatedAmount" />
                <flux:error name="allocatedAmount" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('ملاحظات') }}</flux:label>
                <flux:textarea wire:model="notes" rows="2" />
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
        <flux:heading size="lg">{{ __('حذف الميزانية') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذه الميزانية؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>
