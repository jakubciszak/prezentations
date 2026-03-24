<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Points\Model\PointsAccount;
use App\Points\Model\PointsAccountRepository;

final class InMemoryPointsAccountRepository implements PointsAccountRepository
{
    /** @var array<string, PointsAccount> */
    private array $accounts = [];

    public function save(PointsAccount $account): void
    {
        $this->accounts[$account->memberId] = $account;
    }

    public function findByMemberId(string $memberId): ?PointsAccount
    {
        return $this->accounts[$memberId] ?? null;
    }

    public function getByMemberId(string $memberId): PointsAccount
    {
        return $this->accounts[$memberId]
            ?? throw new \InvalidArgumentException("Points account not found for member: {$memberId}");
    }
}
