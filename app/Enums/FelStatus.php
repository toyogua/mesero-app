<?php

namespace App\Enums;

enum FelStatus: string
{
    case Pending  = 'pending';
    case Issued   = 'issued';
    case Failed   = 'failed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [self::Issued, self::Cancelled], true);
    }
}
