<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Models\WorkOrderLabor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('سجل العمليات')] class extends Component {
    use WithPagination;

    public string $search = '';

    #[Computed]
    public function logs()
    {
        return ActivityLog::query()
            ->with(['user', 'subject'])
            ->when($this->search !== '', fn ($q) => $q->where('action', 'like', "%{$this->search}%"))
            ->latest('created_at')
            ->paginate(20);
    }

    public function translateAction(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'update_status:') => __('تحديث الحالة'),
            str_starts_with($action, 'assigned:') => __('تعيين فني'),
            str_starts_with($action, 'added_part:') => __('إضافة قطعة'),
            str_starts_with($action, 'added_labor:') => __('تسجيل ساعات عمل'),
            str_starts_with($action, 'stock_add:') => __('إضافة للمخزون'),
            str_starts_with($action, 'stock_remove:') => __('صرف من المخزون'),
            $action === 'created' => __('إنشاء'),
            $action === 'updated' => __('تعديل'),
            $action === 'deleted' => __('حذف'),
            $action === 'commented' => __('تعليق'),
            $action === 'added_item' => __('إضافة بند فاتورة'),
            $action === 'deleted_item' => __('حذف بند فاتورة'),
            $action === 'emailed' => __('إرسال بالبريد'),
            $action === 'deleted_labor' => __('حذف ساعات عمل'),
            $action === 'attachment_uploaded' => __('رفع مرفق'),
            $action === 'activated' => __('تفعيل'),
            $action === 'deactivated' => __('إيقاف'),
            default => $action,
        };
    }

    #[Computed]
    public function subjectTypes(): array
    {
        return [
            Asset::class => 'الأصول والمعدات',
            WorkOrder::class => 'أوامر الشغل',
            PreventiveSchedule::class => 'الصيانة الوقائية',
            SparePart::class => 'قطع الغيار',
            Supplier::class => 'الموردون',
            WorkOrderComment::class => 'التعليقات',
            Client::class => 'العملاء',
            Invoice::class => 'الفواتير',
            InvoiceItem::class => 'بنود الفواتير',
            WorkOrderLabor::class => 'ساعات العمل',
            Budget::class => 'الميزانيات',
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('سجل العمليات') }}</flux:heading>
            <flux:subheading>{{ __('تتبع جميع العمليات المنفذة في النظام') }}</flux:subheading>
        </div>
    </div>

    <div class="no-print flex flex-col gap-3 xl:flex-row xl:items-center">
        <flux:input.group class="w-full xl:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث في سجل العمليات...') }}" />
        </flux:input.group>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('المستخدم') }}</flux:table.column>
            <flux:table.column>{{ __('العملية') }}</flux:table.column>
            <flux:table.column>{{ __('النوع') }}</flux:table.column>
            <flux:table.column>{{ __('المحتوى') }}</flux:table.column>
            <flux:table.column>{{ __('الوقت') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row>
                    <flux:table.cell>
                        {{ $log->user?->name ?? __('النظام') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge variant="pill" color="slate">{{ $this->translateAction($log->action) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $this->subjectTypes[$log->model_type] ?? $log->model_type }}</flux:table.cell>
                    <flux:table.cell>{{ $log->subject?->name ?? $log->subject?->invoice_number ?? $log->subject?->code ?? $log->subject?->title ?? '#' . $log->model_id }}</flux:table.cell>
                    <flux:table.cell>{{ $log->created_at?->translatedFormat('d M Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center">
                        {{ __('لا يوجد نشاط مسجل بعد.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>