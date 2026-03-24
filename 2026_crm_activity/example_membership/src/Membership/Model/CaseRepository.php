<?php

declare(strict_types=1);

namespace App\Membership\Model;

interface CaseRepository
{
    public function save(MembershipCase $case): void;

    public function findById(string $caseId): ?MembershipCase;

    public function get(string $caseId): MembershipCase;
}
