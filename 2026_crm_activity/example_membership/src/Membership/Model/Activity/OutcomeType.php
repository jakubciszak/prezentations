<?php

declare(strict_types=1);

namespace App\Membership\Model\Activity;

/**
 * Not every outcome is about points — activities can result in tier changes,
 * benefit unlocks, reward issuance, or other business effects.
 */
enum OutcomeType: string
{
    case PointsEarned = 'points_earned';
    case PointsPending = 'points_pending';
    case PointsActivated = 'points_activated';
    case PointsSpent = 'points_spent';
    case RewardIssued = 'reward_issued';
    case TierChanged = 'tier_changed';
    case BenefitUnlocked = 'benefit_unlocked';
    case Acknowledged = 'acknowledged';
}
