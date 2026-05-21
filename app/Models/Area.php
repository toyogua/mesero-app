<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory, HasUlids, HasAuditLog;

    protected $fillable = ['name', 'display_order', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
