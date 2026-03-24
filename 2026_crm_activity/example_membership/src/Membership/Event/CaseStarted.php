<?php

declare(strict_types=1);

namespace App\Membership\Event;

use DateTimeImmutable;

final readonly class CaseStarted implements CaseEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $caseId,
        public string $templateName,
        public string $activityType,
        public array $activityData,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
