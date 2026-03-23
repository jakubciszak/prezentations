<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

final readonly class Transition
{
    private function __construct(
        public ?StepId $nextStepId,
        public bool $isTerminal,
        public ?CaseOutcome $caseOutcome,
    ) {}

    public static function toNextStep(StepId $nextStepId): self
    {
        return new self(
            nextStepId: $nextStepId,
            isTerminal: false,
            caseOutcome: null,
        );
    }

    public static function terminal(CaseOutcome $caseOutcome): self
    {
        return new self(
            nextStepId: null,
            isTerminal: true,
            caseOutcome: $caseOutcome,
        );
    }
}
