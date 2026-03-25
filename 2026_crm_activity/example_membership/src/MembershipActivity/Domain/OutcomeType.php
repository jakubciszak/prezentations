<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

enum OutcomeType: string
{
    case PointsEarned = 'points_earned';
    case PointsPending = 'points_pending';
    case PointsActivated = 'points_activated';
    case PointsSpent = 'points_spent';
    case RewardIssued = 'reward_issued';
}
