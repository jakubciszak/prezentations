<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

interface CaseEvent
{
    public string $caseId { get; }
    public DateTimeImmutable $occurredAt { get; }
}
