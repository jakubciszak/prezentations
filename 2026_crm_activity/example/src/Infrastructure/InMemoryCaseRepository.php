<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Onboarding\Model\CaseRepository;
use App\Onboarding\Model\OnboardingCase;

final class InMemoryCaseRepository implements CaseRepository
{
    /** @var array<string, OnboardingCase> */
    private array $cases = [];

    public function save(OnboardingCase $case): void
    {
        $this->cases[$case->id->value] = $case;
    }

    public function findById(string $caseId): ?OnboardingCase
    {
        return $this->cases[$caseId] ?? null;
    }

    public function get(string $caseId): OnboardingCase
    {
        return $this->cases[$caseId]
            ?? throw new \InvalidArgumentException("Case not found: {$caseId}");
    }
}
