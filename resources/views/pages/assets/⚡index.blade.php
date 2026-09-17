<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('الأصول والمعدات')] class extends Component {
    use WithFileUploads;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $filterCategoryId = null;

    public ?int $filterLocationId = null;

    public bool $showForm = false;

    public bool $confirmingDelete = false;

    public ?int $assetId = null;

    public ?int $deletingAssetId = null;

    public string $name = '';

    public string $serialNumber = '';

    public ?int $categoryId = null;

    public ?int $locationId = null;

    public ?string $purchaseDate = null;

    public ?string $warrantyExpiry = null;

    public string $status = 'operational';

    public ?string $manufacturer = null;

    public ?string $model = null;

    public $image = null;

    public ?string $existingImage = null;

    #[Computed]
    public function assets()
    {
        return Asset::query()
            ->with(['category', 'location'])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->search.'%')
                        ->orWhere('manufacturer', 'like', '%'.$this->search.'%')
                        ->orWhere('model', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterCategoryId, fn ($query) => $query->where('category_id', $this->filterCategoryId))
            ->when($this->filterLocationId, fn ($query) => $query->where('location_id', $this->filterLocationId))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function categories()
    {
        return AssetCategory::query()->orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return Location::query()->orderBy('name')->get();
    }

    #[Computed]
    public function statuses()
    {
        return [
            'operational' => ['label' => __('تشغيلي'), 'color' => 'success'],
            'under_maintenance' => ['label' => __('قيد الصيانة'), 'color' => 'info'],
            'broken' => ['label' => __('معطوب'), 'color' => 'danger'],
            'retired' => ['label' => __('خارج الخدمة'), 'color' => 'secondary'],
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterStatus', 'filterCategoryId', 'filterLocationId']);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Asset::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(Asset $asset): void
    {
        $this->authorize('update', $asset);

        $this->assetId = $asset->id;
        $this->name = $asset->name;
        $this->serialNumber = $asset->serial_number;
        $this->categoryId = $asset->category_id;
        $this->locationId = $asset->location_id;
        $this->purchaseDate = $asset->purchase_date?->format('Y-m-d');
        $this->warrantyExpiry = $asset->warranty_expiry?->format('Y-m-d');
        $this->status = $asset->status;
        $this->manufacturer = $asset->manufacturer;
        $this->model = $asset->model;
        $this->existingImage = $asset->image;
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
            'assetId', 'name', 'serialNumber', 'categoryId', 'locationId',
            'purchaseDate', 'warrantyExpiry', 'manufacturer', 'model',
            'image', 'existingImage',
        ]);
        $this->status = 'operational';
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->assetId === null) {
            $this->authorize('create', Asset::class);
        } else {
            $this->authorize('update', Asset::findOrFail($this->assetId));
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'serialNumber' => ['required', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($this->assetId)],
            'categoryId' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'locationId' => ['nullable', 'integer', 'exists:locations,id'],
            'purchaseDate' => ['nullable', 'date'],
            'warrantyExpiry' => ['nullable', 'date', 'after_or_equal:purchaseDate'],
            'status' => ['required', Rule::in(['operational', 'under_maintenance', 'broken', 'retired'])],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = $this->existingImage;

        if ($this->image) {
            $imagePath = $this->image->store('assets', 'public');

            if ($this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }
        }

        $data = [
            'name' => $validated['name'],
            'serial_number' => $validated['serialNumber'],
            'category_id' => $validated['categoryId'],
            'location_id' => $validated['locationId'],
            'purchase_date' => $validated['purchaseDate'],
            'warranty_expiry' => $validated['warrantyExpiry'],
            'status' => $validated['status'],
            'manufacturer' => $validated['manufacturer'],
            'model' => $validated['model'],
            'image' => $imagePath,
        ];

        if ($this->assetId === null) {
            $data['created_by'] = auth()->id();
            $asset = Asset::create($data);
            ActivityLog::record('created', $asset);
            Flux::toast(variant: 'success', text: __('تمت إضافة الأصل بنجاح.'));
        } else {
            $asset = Asset::findOrFail($this->assetId);
            $asset->update($data);
            ActivityLog::record('updated', $asset);
            Flux::toast(variant: 'success', text: __('تم تحديث بيانات الأصل.'));
        }

        $this->closeForm();
    }

    public function askDelete(Asset $asset): void
    {
        $this->authorize('delete', $asset);

        $this->deletingAssetId = $asset->id;
        $this->confirmingDelete = true;
    }

    public function delete(): void
    {
        $asset = Asset::findOrFail($this->deletingAssetId);

        $this->authorize('delete', $asset);

        if ($asset->image) {
            Storage::disk('public')->delete($asset->image);
        }

        $asset->delete();

        ActivityLog::record('deleted', $asset);

        Flux::toast(variant: 'success', text: __('تم حذف الأصل.'));

        $this->confirmingDelete = false;
        $this->deletingAssetId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('الأصول والمعدات') }}</flux:heading>
            <flux:subheading>{{ __('إدارة الأصول والمعدات') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('إضافة أصل') }}</flux:button>
    </div>

    <div class="no-print flex flex-col gap-3 md:flex-row md:items-center">
        <flux:input.group class="w-full md:w-72">
            <flux:input.group.prefix icon="magnifying-glass" />
            <flux:input wire:model.live.debounce.500ms="search" placeholder="{{ __('بحث بالاسم أو الرقم التسلسلي...') }}" />
        </flux:input.group>

        <flux:select wire:model.live="filterStatus" class="md:w-44">
            <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
            @foreach ($this->statuses as $status => $info)
                <flux:select.option value="{{ $status }}">{{ $info['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterCategoryId" class="md:w-52">
            <flux:select.option value="">{{ __('كل التصنيفات') }}</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterLocationId" class="md:w-52">
            <flux:select.option value="">{{ __('كل المواقع') }}</flux:select.option>
            @foreach ($this->locations as $location)
                <flux:select.option value="{{ $location->id }}">{{ $location->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$this->assets">
            <flux:table.columns>
                <flux:table.column class="w-24">{{ __('صورة') }}</flux:table.column>
                <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                <flux:table.column>{{ __('رقم تسلسلي') }}</flux:table.column>
                <flux:table.column>{{ __('التصنيف') }}</flux:table.column>
                <flux:table.column>{{ __('الموقع') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
                <flux:table.column align="end">{{ __('إجراءات') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->assets as $asset)
                    <flux:table.row :key="$asset->id" wire:key="asset-{{ $asset->id }}">
                        <flux:table.cell>
                            @if ($asset->image)
                                <img src="{{ asset('storage/'.$asset->image) }}" class="size-10 rounded-md object-cover" alt="{{ $asset->name }}">
                            @else
                                <span class="flex size-10 items-center justify-center rounded-md bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                                    <flux:icon icon="wrench-screwdriver" variant="solid" class="size-5" />
                                </span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('assets.show', $asset) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-white">
                                {{ $asset->name }}
                            </a>
                            @if ($asset->manufacturer || $asset->model)
                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ collect([$asset->manufacturer, $asset->model])->filter()->implode(' - ') }}
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $asset->serial_number }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->location?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$this->statuses[$asset->status]['color']">
                                {{ $this->statuses[$asset->status]['label'] }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="eye" title="{{ __('عرض') }}" :href="route('assets.show', $asset)" wire:navigate />
                                <flux:button variant="ghost" size="sm" icon="pencil-square" title="{{ __('تعديل') }}" wire:click="openEdit({{ $asset->id }})" />
                                <flux:button variant="ghost" size="sm" icon="trash" color="danger" title="{{ __('حذف') }}" wire:click="askDelete({{ $asset->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="!text-center">
                            <div class="py-10 text-center">
                                <flux:heading size="lg">{{ __('لا توجد أصول') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('ابدأ بإضافة أول أصل أو عدّل الفلاتر.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" dismissible class="min-w-sm md:max-w-2xl">
        <flux:heading size="lg">{{ $assetId === null ? __('إضافة أصل جديد') : __('تعديل الأصل') }}</flux:heading>
        <flux:subheading>{{ __('أدخل بيانات الأصل بالأسفل للنموذج، الحقول المميزة بـ * مطلوبة.') }}</flux:subheading>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('اسم الأصل') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="{{ __('مثال: مكبس هيدروليكي') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الرقم التسلسلي') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="serialNumber" placeholder="{{ __('مثال: SN-1024') }}" />
                <flux:error name="serialNumber" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('التصنيف') }}</flux:label>
                <flux:select wire:model="categoryId">
                    <flux:select.option value="">{{ __('— اختر التصنيف —') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="categoryId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الموقع') }}</flux:label>
                <flux:select wire:model="locationId">
                    <flux:select.option value="">{{ __('— اختر الموقع —') }}</flux:select.option>
                    @foreach ($this->locations as $location)
                        <flux:select.option value="{{ $location->id }}">{{ $location->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="locationId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الشركة المصنعة') }}</flux:label>
                <flux:input wire:model="manufacturer" />
                <flux:error name="manufacturer" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الطراز') }}</flux:label>
                <flux:input wire:model="model" />
                <flux:error name="model" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('تاريخ الشراء') }}</flux:label>
                <flux:input type="date" wire:model="purchaseDate" />
                <flux:error name="purchaseDate" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('انتهاء الضمان') }}</flux:label>
                <flux:input type="date" wire:model="warrantyExpiry" />
                <flux:error name="warrantyExpiry" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الحالة') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="status">
                    @foreach ($this->statuses as $status => $info)
                        <flux:select.option value="{{ $status }}">{{ $info['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('صورة الأصل') }}</flux:label>
                <flux:input type="file" wire:model="image" accept="image/*" />
                <flux:error name="image" />
                <div class="mt-2 flex items-center gap-2">
                    @if ($existingImage)
                        <img src="{{ asset('storage/'.$existingImage) }}" class="size-10 rounded-md object-cover" alt="">
                    @endif
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" class="size-10 rounded-md object-cover" alt="">
                    @endif
                </div>
                <flux:text size="xs">{{ __('أقصى حجم 2MB بصيغة JPG أو PNG.') }}</flux:text>
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
        <flux:heading size="lg">{{ __('حذف الأصل') }}</flux:heading>
        <flux:text>{{ __('هل أنت متأكد من حذف هذا الأصل؟ لا يمكن التراجع عن هذه العملية.') }}</flux:text>

        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost" wire:click="$set('confirmingDelete', false)">{{ __('إلغاء') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">{{ __('حذف نهائي') }}</flux:button>
        </div>
    </flux:modal>
</div>