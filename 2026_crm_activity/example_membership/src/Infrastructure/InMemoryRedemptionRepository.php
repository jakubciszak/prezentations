<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Rewards\Model\Redemption;
use App\Rewards\Model\RedemptionRepository;

final class InMemoryRedemptionRepository implements RedemptionRepository
{
    /** @var array<string, Redemption> */
    private array $redemptions = [];

    public function save(Redemption $redemption): void
    {
        $this->redemptions[$redemption->id] = $redemption;
    }

    public function findById(string $id): ?Redemption
    {
        return $this->redemptions[$id] ?? null;
    }

    /** @return Redemption[] */
    public function findByMemberId(string $memberId): array
    {
        return array_values(array_filter(
            $this->redemptions,
            fn(Redemption $r) => $r->memberId === $memberId,
        ));
    }
}
