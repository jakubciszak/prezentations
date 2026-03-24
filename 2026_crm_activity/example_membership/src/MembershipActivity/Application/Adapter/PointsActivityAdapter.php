<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\ActivityType;
use App\MembershipActivity\Domain\Outcome;
use App\MembershipActivity\Domain\OutcomeType;
use App\Points\Api\PointsFacade;

/**
 * Translates purchase/bonus activities → PointsFacade calls → Outcome.
 */
final class PointsActivityAdapter implements ActivityAdapter
{
    public function __construct(
        private readonly PointsFacade $points,
    ) {}

    public function handle(Activity $activity): Outcome
    {
        $calc = match ($activity->type) {
            ActivityType::PurchaseInStore => $this->points->calculateForPurchase(
                $activity->get('amount'),
                $activity->get('transaction_id'),
            ),
            ActivityType::OnlinePurchase => $this->points->calculateForOnlinePurchase(
                $activity->get('amount'),
                $activity->get('order_id'),
            ),
            ActivityType::ChallengeCompleted => $this->points->calculateBonus(
                $activity->get('bonus_points'),
                $activity->get('challenge_id'),
            ),
            ActivityType::BirthdayBonus => $this->points->calculateBonus(
                $activity->get('bonus_points'),
                'birthday',
            ),
            default => throw new \DomainException("Unsupported: {$activity->type->value}"),
        };

        return new Outcome(
            type: $calc->pending ? OutcomeType::PointsPending : OutcomeType::PointsEarned,
            payload: ['points' => $calc->points, 'reference' => $calc->reference],
        );
    }
}
