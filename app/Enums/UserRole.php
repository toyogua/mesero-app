<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';
}
