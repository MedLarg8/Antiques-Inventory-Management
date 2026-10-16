<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case StockEmployee = 'stock_employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator', self::StockEmployee => 'Depot Employee'
        };
    }

    public function capabilities(): string
    {
        return match ($this) {
            self::Admin => 'Manages employee access, organizational records, depot responsibility and inventory catalog settings.',
            self::StockEmployee => 'Views the assigned depot and its authorized inventory. Cannot manage accounts, roles or global catalog settings.',
        };
    }
}
