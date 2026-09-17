<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'unit', 'quantity_in_stock',
        'minimum_quantity', 'unit_price', 'supplier_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function workOrderParts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function isBelowMinimum(): bool
    {
        return $this->quantity_in_stock <= $this->minimum_quantity;
    }
}
