<?php

declare(strict_types=1);

namespace App\Membership\Model;

enum CaseOutcome: string
{
    case PointsAwarded = 'points_awarded';
    case TierUpgraded = 'tier_upgraded';
    case CompletedNoPoints = 'completed_no_points';
    case Rejected = 'rejected';
}
