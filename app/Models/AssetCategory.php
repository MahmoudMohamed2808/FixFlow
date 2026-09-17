<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AssetCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'department_id', 'parent_id'];

    protected static function booted(): void
    {
        // امنع ربط تصنيف فرعي بتصنيف أب من قسم مختلف
        static::saving(function (AssetCategory $category) {
            if ($category->parent_id && $category->department_id) {
                $parentDepartment = static::whereKey($category->parent_id)->value('department_id');

                if ($parentDepartment !== null && $parentDepartment !== $category->department_id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'التصنيف الفرعي لازم يكون تحت تصنيف أب من نفس القسم.',
                    ]);
                }
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssetCategory::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * تصنيفات قسم معين فقط — استخدمها في أي Livewire component بيعرض قايمة اختيار.
     */
    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
