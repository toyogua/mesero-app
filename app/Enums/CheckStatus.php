<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case Void = 'void';

    public function isMutable(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Closed, self::Void], strict: true);
    }
}
