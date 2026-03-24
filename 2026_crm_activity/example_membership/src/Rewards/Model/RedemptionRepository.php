<?php

declare(strict_types=1);

namespace App\Rewards\Model;

interface RedemptionRepository
{
    public function save(Redemption $redemption): void;

    public function findById(string $id): ?Redemption;

    /** @return Redemption[] */
    public function findByMemberId(string $memberId): array;
}
