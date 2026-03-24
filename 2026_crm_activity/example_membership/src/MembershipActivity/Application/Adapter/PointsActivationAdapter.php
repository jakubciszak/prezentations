<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;
use App\MembershipActivity\Domain\OutcomeType;
use App\Points\Api\PointsFacade;
use App\Points\Api\WalletFacade;

/**
 * Translates delivery activity → PointsFacade.confirmActivation + WalletFacade.activateByReference → Outcome.
 */
final class PointsActivationAdapter implements ActivityAdapter
{
    public function __construct(
        private readonly PointsFacade $points,
        private readonly WalletFacade $wallet,
    ) {}

    public function handle(Activity $activity, string $memberId): Outcome
    {
        $confirmation = $this->points->confirmActivation($activity->get('order_id'));

        $result = $this->wallet->activateByReference($memberId, $confirmation->reference);

        return new Outcome(
            type: OutcomeType::PointsActivated,
            payload: [
                'points' => $result->pointsAffected,
                'reference' => $confirmation->reference,
                'active_balance' => $result->activeBalance,
            ],
        );
    }
}
