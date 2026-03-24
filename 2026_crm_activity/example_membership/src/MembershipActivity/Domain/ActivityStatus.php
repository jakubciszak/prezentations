<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

enum ActivityStatus: string
{
    case Recorded = 'recorded';
    case Completed = 'completed';
    case Failed = 'failed';
}
