<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckRating extends Model
{
    use HasUlids;

    protected $fillable = [
        'check_id', 'stars', 'comment', 'ip_address', 'rated_at',
    ];

    protected $casts = [
        'stars'    => 'integer',
        'rated_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
