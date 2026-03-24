<?php

declare(strict_types=1);

namespace App\Membership\Service;

use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\OutcomeType;

/**
 * Handles activation of pending points (e.g., after package delivery).
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
