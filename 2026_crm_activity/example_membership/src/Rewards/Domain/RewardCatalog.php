<?php

declare(strict_types=1);

namespace App\Rewards\Domain;

/**
 * Port — the Rewards context exposes its catalog through this interface.
 */
interface RewardCatalog
{
    public function findById(string $rewardId): ?Reward;

    /** @return Reward[] */
    public function findAvailableForPoints(int $points): array;
}
