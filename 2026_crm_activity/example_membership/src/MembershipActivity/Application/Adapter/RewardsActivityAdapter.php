<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;
use App\MembershipActivity\Domain\OutcomeType;
use App\Rewards\Api\RewardsFacade;

/**
 * Translates reward redemption activity → RewardsFacade → Outcome.
 */
final class RewardsActivityAdapter implements ActivityAdapter
{
    public function __construct(
        private readonly RewardsFacade $rewards,
    ) {}

    public function handle(Activity $activity): Outcome
    {
        $result = $this->rewards->processRedemption($activity->get('reward_id'));

        return new Outcome(
            type: OutcomeType::RewardIssued,
            payload: [
                'reward_id' => $result->rewardId,
                'reward_name' => $result->rewardName,
                'points_spent' => $result->pointsCost,
            ],
        );
    }
}
