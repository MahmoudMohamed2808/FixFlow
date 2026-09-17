<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id', 'spare_part_id', 'quantity_used', 'unit_cost',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (WorkOrderPart $workOrderPart) {
            $workOrderPart->sparePart()->decrement('quantity_in_stock', $workOrderPart->quantity_used);
        });

        static::deleted(function (WorkOrderPart $workOrderPart) {
            $workOrderPart->sparePart()->increment('quantity_in_stock', $workOrderPart->quantity_used);
        });

        static::saved(fn (WorkOrderPart $part) => $part->workOrder?->recalculateCost());
        static::deleted(fn (WorkOrderPart $part) => $part->workOrder?->recalculateCost());
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
