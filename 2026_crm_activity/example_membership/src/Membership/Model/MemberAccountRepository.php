<?php

declare(strict_types=1);

namespace App\Membership\Model;

interface MemberAccountRepository
{
    public function save(MemberAccount $account): void;

    public function findById(string $memberId): ?MemberAccount;

    public function get(string $memberId): MemberAccount;
}
