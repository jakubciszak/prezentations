<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

final readonly class CaseFinished implements CaseEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $caseId,
        public string $outcome,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function caseId(): string
    {
        return $this->caseId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
