<?php

declare(strict_types=1);

namespace App\Rewards\Application;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\OutcomeType;
use App\Rewards\Domain\RewardCatalog;
use App\SharedKernel\ActivityService;
use App\SharedKernel\ServiceResponse;

/**
 * Rewards bounded context — processes reward redemption.
 *
 * Looks up the reward in the catalog, validates it, and responds
 * with the cost and details. The Activity only carries reward_id —
 * this service decides how many points it costs and whether it's available.
 */
final class RewardRedemptionService implements ActivityService
{
    public function __construct(
        private readonly RewardCatalog $catalog,
    ) {}

    public function process(Activity $activity): ServiceResponse
    {
        $rewardId = $activity->get('reward_id');
        $reward = $this->catalog->findById($rewardId);

        if ($reward === null) {
            throw new \DomainException("Reward not found: {$rewardId}");
        }

        if (!$reward->active) {
            throw new \DomainException("Reward '{$rewardId}' is not active");
        }

        return new ServiceResponse(
            activityId: $activity->id->value,
            outcomeType: OutcomeType::RewardIssued,
            payload: [
                'reward_id' => $reward->id,
                'reward_name' => $reward->name,
                'points_spent' => $reward->pointsCost,
            ],
        );
    }
}
