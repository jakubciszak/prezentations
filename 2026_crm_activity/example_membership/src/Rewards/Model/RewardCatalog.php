<?php

declare(strict_types=1);

namespace App\Rewards\Model;

interface RewardCatalog
{
    public function findById(string $rewardId): ?Reward;

    /** @return Reward[] */
    public function findAvailableForPoints(int $points): array;

    /** @return Reward[] */
    public function all(): array;
}
