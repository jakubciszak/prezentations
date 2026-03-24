<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Membership\Model\MemberAccount;
use App\Membership\Model\MemberAccountRepository;

final class InMemoryMemberAccountRepository implements MemberAccountRepository
{
    /** @var array<string, MemberAccount> */
    private array $accounts = [];

    public function save(MemberAccount $account): void
    {
        $this->accounts[$account->id->value] = $account;
    }

    public function findById(string $memberId): ?MemberAccount
    {
        return $this->accounts[$memberId] ?? null;
    }

    public function get(string $memberId): MemberAccount
    {
        return $this->accounts[$memberId]
            ?? throw new \InvalidArgumentException("Member account not found: {$memberId}");
    }
}
