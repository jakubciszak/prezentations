<?php

declare(strict_types=1);

namespace App\Membership\Model\Reward;

final readonly class Reward
{
    public function __construct(
        public string $id,
        public string $name,
        public int $pointsCost,
        public RewardType $type,
        public string $description,
        public bool $active = true,
    ) {}
}
