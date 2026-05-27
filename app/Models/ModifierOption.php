<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierOption extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['modifier_group_id', 'name', 'price_delta', 'display_order', 'active'];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'display_order' => 'integer',
        'active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function ingredientLines(): HasMany
    {
        return $this->hasMany(ModifierOptionIngredient::class);
    }
}
