<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClose extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'period_from', 'period_to',
        'checks_count', 'subtotal', 'tax', 'tip', 'total',
        'cash_counted', 'card_counted', 'notes',
    ];

    protected $casts = [
        'period_from'  => 'datetime',
        'period_to'    => 'datetime',
        'checks_count' => 'integer',
        'subtotal'     => 'decimal:2',
        'tax'          => 'decimal:2',
        'tip'          => 'decimal:2',
        'total'        => 'decimal:2',
        'cash_counted' => 'decimal:2',
        'card_counted' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
