<?php

declare(strict_types=1);

namespace App\Rewards\Model;

enum RedemptionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
