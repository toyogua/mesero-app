<?php

namespace App\Models;

use App\Enums\CheckItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class CheckItem extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'check_id', 'menu_item_id', 'kitchen_station_id',
        'name_snapshot', 'price_snapshot', 'quantity',
        'notes', 'status', 'sent_at', 'ready_at', 'served_at',
    ];

    protected $casts = [
        'status' => CheckItemStatus::class,
        'price_snapshot' => 'decimal:2',
        'quantity' => 'integer',
        'sent_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function modifiers()
    {
        return $this->hasMany(CheckItemModifier::class);
    }

    /**
     * Apply a state transition. Validates that the move is legal and
     * stamps the matching timestamp column. Throws if the transition is invalid.
     */
    public function transitionTo(CheckItemStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new RuntimeException(
                "Invalid transition for check item {$this->id}: {$this->status->value} → {$target->value}"
            );
        }

        $stamps = match ($target) {
            CheckItemStatus::Ordered  => ['sent_at' => now()],
            CheckItemStatus::Ready    => ['ready_at' => now()],
            CheckItemStatus::Served   => ['served_at' => now()],
            default                   => [],
        };

        $this->forceFill(['status' => $target] + $stamps)->save();
    }
}
