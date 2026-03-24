<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

interface CaseRepository
{
    public function save(OnboardingCase $case): void;

    public function findById(string $caseId): ?OnboardingCase;

    public function get(string $caseId): OnboardingCase;
}
