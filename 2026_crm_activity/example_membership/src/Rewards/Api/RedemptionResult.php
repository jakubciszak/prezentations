<?php

declare(strict_types=1);

namespace App\Rewards\Api;

/**
 * Result of processing a reward redemption — what it costs.
 */
final readonly class RedemptionResult
{
    public function __construct(
        public string $rewardId,
        public string $rewardName,
        public int $pointsCost,
    ) {}
}
