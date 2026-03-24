<?php

declare(strict_types=1);

namespace App\Points\Application;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\ActivityType;
use App\MembershipActivity\Domain\OutcomeType;
use App\SharedKernel\ActivityService;
use App\SharedKernel\ServiceResponse;

/**
 * Points bounded context — calculates points for various activity types.
 *
 * This is where the business logic lives — multipliers, bonuses, etc.
 * The Activity itself carries no knowledge of point values.
 */
final class PointsCalculationService implements ActivityService
{
    public function process(Activity $activity): ServiceResponse
    {
        return match ($activity->type) {
            ActivityType::PurchaseInStore => new ServiceResponse(
                activityId: $activity->id->value,
                outcomeType: OutcomeType::PointsEarned,
                payload: [
                    'points' => (int) floor($activity->get('amount')),
                    'reference' => $activity->get('transaction_id'),
                ],
            ),

            ActivityType::OnlinePurchase => new ServiceResponse(
                activityId: $activity->id->value,
                outcomeType: OutcomeType::PointsPending,
                payload: [
                    'points' => (int) floor($activity->get('amount') * 1.5),
                    'reference' => $activity->get('order_id'),
                ],
            ),

            ActivityType::ChallengeCompleted => new ServiceResponse(
                activityId: $activity->id->value,
                outcomeType: OutcomeType::PointsEarned,
                payload: [
                    'points' => $activity->get('bonus_points'),
                    'reference' => $activity->get('challenge_id'),
                ],
            ),

            ActivityType::BirthdayBonus => new ServiceResponse(
                activityId: $activity->id->value,
                outcomeType: OutcomeType::PointsEarned,
                payload: [
                    'points' => $activity->get('bonus_points'),
                    'reference' => 'birthday',
                ],
            ),

            default => throw new \DomainException(
                "Unsupported activity type for points calculation: {$activity->type->value}"
            ),
        };
    }
}
