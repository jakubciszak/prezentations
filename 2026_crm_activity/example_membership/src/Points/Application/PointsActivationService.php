<?php

declare(strict_types=1);

namespace App\Points\Application;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\OutcomeType;
use App\SharedKernel\ActivityService;
use App\SharedKernel\ServiceResponse;

/**
 * Points bounded context — handles activation of pending points.
 *
 * In production this could verify delivery status with a shipping API
 * before emitting the activation response.
 */
final class PointsActivationService implements ActivityService
{
    public function process(Activity $activity): ServiceResponse
    {
        return new ServiceResponse(
            activityId: $activity->id->value,
            outcomeType: OutcomeType::PointsActivated,
            payload: [
                'reference' => $activity->get('order_id'),
            ],
        );
    }
}
