<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'company_name', 'phone', 'email', 'address', 'notes',
    ];

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * إجمالي المبالغ المدفوعة فعليًا من العميل ده.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->invoices()->where('status', 'paid')->sum('total_amount');
    }

    /**
     * إجمالي المستحق (فواتير اتبعتت أو متأخرة ولسه ما اتحصلتش).
     */
    public function getTotalOutstandingAttribute(): float
    {
        return (float) $this->invoices()->whereIn('status', ['sent', 'overdue'])->sum('total_amount');
    }
}
