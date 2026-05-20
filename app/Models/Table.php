<?php

namespace App\Models;

use App\Enums\CheckStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['area_id', 'name', 'capacity', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'capacity' => 'integer',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * Currently open check on this table, if any.
     */
    public function openCheck(): HasOne
    {
        return $this->hasOne(Check::class)
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::Closing->value]);
    }

    public function isOccupied(): bool
    {
        return $this->openCheck()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
