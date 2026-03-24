<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

final readonly class ActionInitialized implements CaseEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $caseId,
        public string $stageId,
        public string $stepId,
        public string $service,
        public string $action,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
