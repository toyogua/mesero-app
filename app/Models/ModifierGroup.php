<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'min_selections', 'max_selections', 'display_order'];

    protected $casts = [
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'display_order'  => 'integer',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('display_order');
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_group')
            ->withPivot('display_order')
            ->orderByPivot('display_order');
    }
}
