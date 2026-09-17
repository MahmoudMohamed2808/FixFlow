<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'client_id', 'work_order_id',
        'issue_date', 'due_date', 'status',
        'subtotal', 'tax', 'total_amount', 'paid_at', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // أول مرة تتحفظ الفاتورة، لو مفيش رقم متحدد، ولّد رقم تلقائي زي INV-00001
        static::creating(function (Invoice $invoice) {
            if (! $invoice->invoice_number) {
                $lastId = static::max('id') + 1;
                $invoice->invoice_number = 'INV-' . str_pad((string) $lastId, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * أعد حساب subtotal و total_amount من بنود الفاتورة.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');

        $this->updateQuietly([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $this->tax,
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid', 'paid_at' => now()]);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
