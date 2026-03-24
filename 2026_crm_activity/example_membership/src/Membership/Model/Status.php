<?php

declare(strict_types=1);

namespace App\Membership\Model;

enum Status: string
{
    case Initialized = 'initialized';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
