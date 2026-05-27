<?php

namespace App\Models;

use App\Enums\CheckItemStatus;
use App\Enums\CheckSource;
use App\Enums\CheckStatus;
use App\Enums\CheckType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'number', 'table_id', 'waiter_user_id', 'status', 'order_type', 'source', 'covers',
        'notes', 'subtotal', 'tax', 'tip', 'total',
        'opened_at', 'closed_at', 'transferred_from',
        'customer_name', 'customer_phone', 'customer_address',
    ];

    protected $casts = [
        'status'     => CheckStatus::class,
        'order_type' => CheckType::class,
        'source'     => CheckSource::class,
        'covers' => 'integer',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'tip' => 'decimal:2',
        'total' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckItem::class);
    }

    public function felInvoice()
    {
        return $this->hasOne(FelInvoice::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(PaymentSplit::class);
    }

    public function rating(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CheckRating::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', CheckStatus::Open);
    }

    /**
     * True only when all items are in a final state (served or cancelled).
     */
    public function isReadyToClose(): bool
    {
        return ! $this->items()
            ->whereNotIn('status', [
                CheckItemStatus::Served->value,
                CheckItemStatus::Cancelled->value,
            ])->exists();
    }

    /**
     * Recompute subtotal / tax / total from non-cancelled items.
     * Prices are IVA-inclusive (Guatemala). IVA is extracted for accounting display.
     */
    public function recalculate(): void
    {
        $items = $this->items()
            ->where('status', '!=', CheckItemStatus::Cancelled->value)
            ->with('modifiers')
            ->get();

        $totalPrices = $items->sum(
            fn ($i) => ($i->price_snapshot + $i->modifiers->sum('price_snapshot')) * $i->quantity
        );
        $taxRate = (float) config('restaurant.iva_rate');
        $subtotal = round($totalPrices / (1 + $taxRate), 2);
        $tax = round($totalPrices - $subtotal, 2);
        $total = $totalPrices + (float) $this->tip;

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ])->save();
    }
}
