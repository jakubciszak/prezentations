<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

final readonly class PointsActivated implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public int $points,
        public string $reference,
        public int $activeBalance,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
