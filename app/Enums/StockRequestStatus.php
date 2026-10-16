<?php

namespace App\Enums;

enum StockRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',self::Pending => 'Pending approval',self::Approved => 'Approved / awaiting execution',self::Rejected => 'Rejected',self::Completed => 'Completed',self::Cancelled => 'Cancelled'
        };
    }
}
