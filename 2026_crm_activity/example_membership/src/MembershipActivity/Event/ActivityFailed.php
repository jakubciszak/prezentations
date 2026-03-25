<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

/**
 * An activity processing has failed.
 */
final readonly class ActivityFailed implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public string $activityType,
        public string $reason,
        public array $payload,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
