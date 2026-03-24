<?php

declare(strict_types=1);

namespace App\SharedKernel;

use App\MembershipActivity\Domain\Activity;

/**
 * Contract between the MembershipActivity context and external service contexts.
 *
 * Each bounded context (Points, Rewards, ...) implements this interface
 * to process activities and return a ServiceResponse.
 *
 * In production, services would emit async events instead of returning directly.
 */
interface ActivityService
{
    public function process(Activity $activity): ServiceResponse;
}
