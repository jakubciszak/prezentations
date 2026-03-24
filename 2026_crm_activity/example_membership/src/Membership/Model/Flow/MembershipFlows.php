<?php

declare(strict_types=1);

namespace App\Membership\Model\Flow;

use App\Membership\Model\Activity\ActivityType;

/**
 * The complete flow configuration for the loyalty membership program.
 *
 * One place, fully declarative: activity type → service name.
 * Adding a new activity type means adding one line here
 * and implementing the service.
 */
final class MembershipFlows
{
    public static function standard(): FlowRegistry
    {
        return new FlowRegistry(
            ActivityFlow::route(ActivityType::PurchaseInStore, 'points_calculation'),
            ActivityFlow::route(ActivityType::OnlinePurchase, 'points_calculation'),
            ActivityFlow::route(ActivityType::PackageDelivered, 'points_activation'),
            ActivityFlow::route(ActivityType::ChallengeCompleted, 'points_calculation'),
            ActivityFlow::route(ActivityType::BirthdayBonus, 'points_calculation'),
            ActivityFlow::route(ActivityType::RewardRedemption, 'reward_service'),
        );
    }
}
