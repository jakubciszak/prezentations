<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

/**
 * An activity has been recorded and dispatched for processing.
 */
final readonly class ActivityInitialized implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public string $activityType,
        public array $payload,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
