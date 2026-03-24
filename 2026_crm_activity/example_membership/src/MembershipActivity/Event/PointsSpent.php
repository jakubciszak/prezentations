<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

final readonly class PointsSpent implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public int $points,
        public int $balance,
        public string $description,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
