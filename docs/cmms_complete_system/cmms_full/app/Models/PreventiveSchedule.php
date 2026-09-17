<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'title', 'frequency_type', 'frequency_value',
        'last_generated_date', 'next_due_date', 'is_active',
    ];

    protected $casts = [
        'last_generated_date' => 'date',
        'next_due_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'schedule_id');
    }
}
