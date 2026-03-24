<?php

declare(strict_types=1);

namespace App\Membership\Event;

use DateTimeImmutable;

final readonly class PointsPending implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public int $points,
        public string $awaitingReference,
        public string $description,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
