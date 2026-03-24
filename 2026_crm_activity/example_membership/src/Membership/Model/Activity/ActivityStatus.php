<?php

declare(strict_types=1);

namespace App\Membership\Model\Activity;

enum ActivityStatus: string
{
    case Recorded = 'recorded';
    case Completed = 'completed';
    case Failed = 'failed';
}
