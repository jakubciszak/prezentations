<?php

declare(strict_types=1);

namespace App\Membership\Model\Reward;

interface RewardCatalog
{
    public function findById(string $rewardId): ?Reward;

    /** @return Reward[] */
    public function findAvailableForPoints(int $points): array;
}
