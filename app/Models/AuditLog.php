<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'auditable_label', 'old_values', 'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Shorten fully-qualified class name to just the model name. */
    public function entityName(): string
    {
        return class_basename($this->auditable_type);
    }
}
