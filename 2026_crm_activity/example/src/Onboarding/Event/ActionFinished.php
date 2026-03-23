<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

final readonly class ActionFinished implements CaseEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $caseId,
        public string $stageId,
        public string $stepId,
        public string $outcome,
        public bool $isTerminal,
        public ?string $caseOutcome,
        public ?string $nextStepId,
        public array $metadata = [],
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
