<?php

declare(strict_types=1);

namespace App\Points\Model;

interface PointsAccountRepository
{
    public function save(PointsAccount $account): void;

    public function findByMemberId(string $memberId): ?PointsAccount;

    public function getByMemberId(string $memberId): PointsAccount;
}
