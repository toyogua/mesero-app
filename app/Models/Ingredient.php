<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name', 'unit', 'quantity_on_hand', 'minimum_stock', 'cost_price', 'active',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'minimum_stock'    => 'decimal:4',
        'cost_price'       => 'decimal:4',
        'active'           => 'boolean',
    ];

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->minimum_stock;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'minimum_stock');
    }
}
