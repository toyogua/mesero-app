<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckItemModifier extends Model
{
    use HasUlids;

    protected $fillable = [
        'check_item_id', 'modifier_option_id', 'name_snapshot', 'price_snapshot',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
    ];

    public function checkItem(): BelongsTo
    {
        return $this->belongsTo(CheckItem::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }
}
