<?php

declare(strict_types=1);

namespace App\Rewards\Model;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class Redemption
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    private RedemptionStatus $status;

    public function __construct(
        public readonly string $memberId,
        public readonly Reward $reward,
        public readonly int $pointsSpent,
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = new DateTimeImmutable();
        $this->status = RedemptionStatus::Pending;
    }

    public function confirm(): void
    {
        if ($this->status !== RedemptionStatus::Pending) {
            throw new \DomainException("Cannot confirm redemption in status '{$this->status->value}'");
        }
        $this->status = RedemptionStatus::Confirmed;
    }

    public function cancel(): void
    {
        if ($this->status !== RedemptionStatus::Pending) {
            throw new \DomainException("Cannot cancel redemption in status '{$this->status->value}'");
        }
        $this->status = RedemptionStatus::Cancelled;
    }

    public function status(): RedemptionStatus
    {
        return $this->status;
    }
}
