<?php

declare(strict_types=1);

namespace App\Membership\Model\Points;

enum EntryStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
}
