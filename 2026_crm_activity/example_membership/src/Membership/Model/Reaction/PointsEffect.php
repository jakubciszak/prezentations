<?php

declare(strict_types=1);

namespace App\Membership\Model\Reaction;

enum PointsEffect: string
{
    case Earn = 'earn';
    case EarnPending = 'earn_pending';
    case EarnBonus = 'earn_bonus';
    case Activate = 'activate';
    case Spend = 'spend';
}
