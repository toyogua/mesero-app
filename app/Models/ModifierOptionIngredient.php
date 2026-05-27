<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOptionIngredient extends Model
{
    use HasUlids;

    protected $fillable = ['modifier_option_id', 'ingredient_id', 'quantity_used'];

    protected $casts = ['quantity_used' => 'decimal:4'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
