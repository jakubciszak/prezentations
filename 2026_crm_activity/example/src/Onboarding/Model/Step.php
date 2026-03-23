<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

use DateTimeImmutable;

/** Activity - the individual action within a Stage */
final class Step
{
    private Status $status;
    private ?Outcome $outcome = null;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;

    public function __construct(
        public readonly string $stepId,
        public readonly string $name,
        public readonly string $service,
        public readonly string $action,
    ) {
        $this->status = Status::Initialized;
    }

    public function markPending(): void
    {
        $this->status = Status::Pending;
        $this->startedAt = new DateTimeImmutable();
    }

    public function complete(Outcome $outcome): void
    {
        $this->status = Status::Completed;
        $this->outcome = $outcome;
        $this->completedAt = new DateTimeImmutable();
    }

    public function fail(Outcome $outcome): void
    {
        $this->status = Status::Failed;
        $this->outcome = $outcome;
        $this->completedAt = new DateTimeImmutable();
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function outcome(): ?Outcome
    {
        return $this->outcome;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }
}
