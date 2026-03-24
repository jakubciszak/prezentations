<?php

declare(strict_types=1);

namespace App\Membership\Service;

use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\Reward\RewardCatalog;

/**
 * Processes reward redemption — looks up the reward, validates it,
 * and responds with the cost and details.
 *
 * The Activity only carries the reward_id. This service decides
 * how many points it costs and whether it's available.
 */
final class RewardService implements ActivityService
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
