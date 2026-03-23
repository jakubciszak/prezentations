<?php

declare(strict_types=1);

namespace App\Onboarding\Event;

use DateTimeImmutable;

interface CaseEvent
{
    public function caseId(): string;
    public function occurredAt(): DateTimeImmutable;
}
