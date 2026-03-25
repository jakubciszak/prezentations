<?php

declare(strict_types=1);

namespace App\Points\Domain;

enum EntryType: string
{
    case Earn = 'earn';
    case BonusEarn = 'bonus_earn';
    case Spend = 'spend';
}
