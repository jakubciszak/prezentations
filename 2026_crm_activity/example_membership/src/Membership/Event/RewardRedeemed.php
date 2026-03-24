<?php

declare(strict_types=1);

namespace App\Membership\Event;

use DateTimeImmutable;

final readonly class RewardRedeemed implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $activityId,
        public string $redemptionId,
        public string $rewardId,
        public string $rewardName,
        public int $pointsSpent,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
