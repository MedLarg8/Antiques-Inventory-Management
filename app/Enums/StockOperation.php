<?php

namespace App\Enums;

enum StockOperation: string
{
    case Entry = 'entry';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Stock entry',self::Exit => 'Stock exit'
        };
    }
}
