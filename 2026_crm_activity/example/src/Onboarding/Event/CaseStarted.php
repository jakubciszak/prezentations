<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

final readonly class CaseStarted implements CaseEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $caseId,
        public string $templateName,
        public string $clientType,
        public array $clientData,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
