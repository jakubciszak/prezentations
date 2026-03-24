<?php

declare(strict_types=1);

namespace App\Rewards\Api;

/**
 * Public API of the Rewards bounded context.
 *
 * Knows nothing about Activity or MemberAccount.
 * Validates rewards and returns redemption details.
 */
interface RewardsFacade
{
    public function processRedemption(string $rewardId): RedemptionResult;
}
