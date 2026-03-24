<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;
use App\MembershipActivity\Domain\OutcomeType;
use App\Points\Api\PointsFacade;

/**
 * Translates delivery activity → PointsFacade.confirmActivation → Outcome.
 */
final class PointsActivationAdapter implements ActivityAdapter
{
    public function __construct(
        private readonly PointsFacade $points,
    ) {}

    public function handle(Activity $activity): Outcome
    {
        $result = $this->points->confirmActivation($activity->get('order_id'));

        return new Outcome(
            type: OutcomeType::PointsActivated,
            payload: ['reference' => $result->reference],
        );
    }
}
