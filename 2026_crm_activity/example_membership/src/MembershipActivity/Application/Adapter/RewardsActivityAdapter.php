<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;
use App\MembershipActivity\Domain\OutcomeType;
use App\Points\Api\WalletFacade;
use App\Rewards\Api\RewardsFacade;

/**
 * Translates reward redemption → RewardsFacade (lookup) + WalletFacade (spend) → Outcome.
 */
final class RewardsActivityAdapter implements ActivityAdapter
{
    public function __construct(
        private readonly RewardsFacade $rewards,
        private readonly WalletFacade $wallet,
    ) {}

    public function handle(Activity $activity, string $memberId): Outcome
    {
        $redemption = $this->rewards->processRedemption($activity->get('reward_id'));

        $result = $this->wallet->spend(
            $memberId,
            $redemption->pointsCost,
            'reward_redemption',
            "reward:{$redemption->rewardId}",
        );

        return new Outcome(
            type: OutcomeType::RewardIssued,
            payload: [
                'reward_id' => $redemption->rewardId,
                'reward_name' => $redemption->rewardName,
                'points_spent' => $redemption->pointsCost,
                'active_balance' => $result->activeBalance,
            ],
        );
    }
}
