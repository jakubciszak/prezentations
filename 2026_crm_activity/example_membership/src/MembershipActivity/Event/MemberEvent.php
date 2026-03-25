<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

/**
 * Generic membership event — knows nothing about points, rewards, or any other context.
 * Carries activity type + payload. Downstream contexts interpret the payload.
 */
interface MemberEvent
{
    public string $memberId { get; }
    public DateTimeImmutable $occurredAt { get; }
}
