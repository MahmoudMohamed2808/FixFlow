<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'asset_id', 'client_id', 'type', 'priority', 'status', 'description',
        'reported_by', 'assigned_to', 'schedule_id',
        'scheduled_date', 'started_at', 'completed_at', 'cost',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PreventiveSchedule::class, 'schedule_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function laborEntries(): HasMany
    {
        return $this->hasMany(WorkOrderLabor::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class);
    }

    public function downtimeRecord(): HasOne
    {
        return $this->hasOne(DowntimeRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * تكلفة قطع الغيار المستخدمة فقط (مصروف داخلي).
     */
    public function getPartsCostAttribute(): float
    {
        return (float) $this->parts->sum(fn (WorkOrderPart $part) => $part->quantity_used * $part->unit_cost);
    }

    /**
     * تكلفة أجور الفنيين فقط (مصروف داخلي).
     */
    public function getLaborCostAttribute(): float
    {
        return (float) $this->laborEntries->sum('cost');
    }

    /**
     * إعادة حساب المصروف الإجمالي (قطع غيار + أجور) وتخزينه في عمود cost.
     */
    public function recalculateCost(): void
    {
        $this->loadMissing(['parts', 'laborEntries']);

        $total = $this->parts_cost + $this->labor_cost;

        $this->updateQuietly(['cost' => $total]);
    }
}
