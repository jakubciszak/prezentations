<?php

declare(strict_types=1);

namespace App\Points\Domain;

enum EntryStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
}
