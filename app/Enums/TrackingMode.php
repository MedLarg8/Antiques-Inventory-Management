<?php

namespace App\Enums;

enum TrackingMode: string
{
    case Consumable = 'quantity_consumable';
    case Reusable = 'quantity_reusable';
    case Individual = 'individual_asset';
}
