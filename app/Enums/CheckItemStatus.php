<?php

namespace App\Enums;

enum CheckItemStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    /**
     * Can the item transition into the given target status?
     * Returns false for invalid transitions.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ([$this, $target]) {
            [self::Draft, self::Ordered],
            [self::Draft, self::Cancelled],
            [self::Ordered, self::Preparing],
            [self::Ordered, self::Cancelled],
            [self::Preparing, self::Ready],
            [self::Ready, self::Served]   => true,
            default                        => false,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Served, self::Cancelled], strict: true);
    }

    public function isVisibleInKitchen(): bool
    {
        return in_array($this, [self::Ordered, self::Preparing], strict: true);
    }
}
