<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DowntimeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'work_order_id', 'start_time', 'end_time', 'duration_minutes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected static function booted(): void
    {
        // حساب المدة تلقائيًا لما يتسجل وقت الانتهاء
        static::saving(function (DowntimeRecord $record) {
            if ($record->start_time && $record->end_time) {
                $record->duration_minutes = $record->start_time->diffInMinutes($record->end_time);
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
