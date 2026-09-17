<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLabor extends Model
{
    use HasFactory;

    protected $table = 'work_order_labor';

    protected $fillable = [
        'work_order_id', 'user_id', 'hours_spent', 'hourly_rate', 'cost',
    ];

    protected $casts = [
        'hours_spent' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkOrderLabor $labor) {
            if (! $labor->cost) {
                $labor->cost = round($labor->hours_spent * $labor->hourly_rate, 2);
            }
        });

        static::saved(fn (WorkOrderLabor $labor) => $labor->workOrder?->recalculateCost());
        static::deleted(fn (WorkOrderLabor $labor) => $labor->workOrder?->recalculateCost());
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
