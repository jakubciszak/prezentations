<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

/**
 * An activity has been successfully processed.
 * The outcome payload carries context-specific details (points, reward info, etc.)
 * but this event itself is agnostic — downstream contexts interpret the payload.
 */
final readonly class ActivityCompleted implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public string $activityType,
        public string $outcomeType,
        public array $outcomePayload,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
