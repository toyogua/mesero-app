<?php

namespace App\Enums;

enum CheckType: string
{
    case DineIn  = 'dine_in';
    case Takeout = 'takeout';

    public function label(): string
    {
        return match ($this) {
            self::DineIn  => 'Salón',
            self::Takeout => 'Para llevar',
        };
    }
}
