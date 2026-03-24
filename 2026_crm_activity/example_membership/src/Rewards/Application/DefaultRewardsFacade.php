<?php

declare(strict_types=1);

namespace App\Rewards\Application;

use App\Rewards\Api\RedemptionResult;
use App\Rewards\Api\RewardsFacade;
use App\Rewards\Domain\RewardCatalog;

/**
 * Default implementation — looks up catalog, validates, returns cost.
 * Zero knowledge of Activity or MemberAccount.
 */
final class DefaultRewardsFacade implements RewardsFacade
{
    public function __construct(
        private readonly RewardCatalog $catalog,
    ) {}

    public function processRedemption(string $rewardId): RedemptionResult
    {
        $reward = $this->catalog->findById($rewardId);

        if ($reward === null) {
            throw new \DomainException("Reward not found: {$rewardId}");
        }

        if (!$reward->active) {
            throw new \DomainException("Reward '{$rewardId}' is not active");
        }

        return new RedemptionResult(
            rewardId: $reward->id,
            rewardName: $reward->name,
            pointsCost: $reward->pointsCost,
        );
    }
}
