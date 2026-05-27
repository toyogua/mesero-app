<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_name', 'address', 'phone', 'logo_path', 'display_pin',
        'online_ordering_enabled', 'online_ordering_min_amount',
    ];

    protected $casts = [
        'online_ordering_enabled'    => 'boolean',
        'online_ordering_min_amount' => 'decimal:2',
    ];

    /** Siempre devuelve la única fila, creándola si no existe. */
    public static function instance(): static
    {
        return static::firstOrCreate([], []);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }
}
