<?php

declare(strict_types=1);

namespace App\Points\Model;

enum EntryType: string
{
    case Earn = 'earn';
    case BonusEarn = 'bonus_earn';
    case Spend = 'spend';
    case Refund = 'refund';
    case Adjust = 'adjust';
    case Expire = 'expire';
}
