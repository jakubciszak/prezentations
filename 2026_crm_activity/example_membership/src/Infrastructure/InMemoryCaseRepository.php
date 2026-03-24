<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Membership\Model\CaseRepository;
use App\Membership\Model\MembershipCase;

final class InMemoryCaseRepository implements CaseRepository
{
    /** @var array<string, MembershipCase> */
    private array $cases = [];

    public function save(MembershipCase $case): void
    {
        $this->cases[$case->id->value] = $case;
    }

    public function findById(string $caseId): ?MembershipCase
    {
        return $this->cases[$caseId] ?? null;
    }

    public function get(string $caseId): MembershipCase
    {
        return $this->cases[$caseId]
            ?? throw new \InvalidArgumentException("Case not found: {$caseId}");
    }
}
