<?php

declare(strict_types=1);

namespace App\Membership\Service;

use App\Membership\Model\Activity\Activity;

/**
 * Processes an activity and returns a response.
 *
 * In production, the service would emit an async event instead of returning directly.
 * The interface represents the contract — the delivery mechanism is infrastructure.
 */
interface ActivityService
{
    public function process(Activity $activity): ServiceResponse;
}
