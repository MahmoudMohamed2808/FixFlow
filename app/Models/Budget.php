<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'department_id', 'period_start', 'period_end',
        'allocated_amount', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'allocated_amount' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workOrdersQuery()
    {
        return WorkOrder::query()
            ->whereBetween('scheduled_date', [$this->period_start, $this->period_end])
            ->when($this->department_id, function ($query) {
                $query->whereHas('asset.location', function ($q) {
                    $q->where('department_id', $this->department_id);
                });
            });
    }

    public function getSpentAmountAttribute(): float
    {
        return (float) $this->workOrdersQuery()->sum('cost');
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->allocated_amount - $this->spent_amount;
    }

    public function getUsagePercentAttribute(): float
    {
        if ((float) $this->allocated_amount <= 0) {
            return 0;
        }

        return round(($this->spent_amount / (float) $this->allocated_amount) * 100, 1);
    }

    public function isOverBudget(): bool
    {
        return $this->spent_amount > (float) $this->allocated_amount;
    }
}
