<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

/**
 * Pending state - step has been dispatched to an external service, awaiting response.
 * Allowed transitions: complete() or fail()
 */
final readonly class PendingState implements StepState
{
    public function __construct(
        private DateTimeImmutable $startedAt,
    ) {}

    public function status(): Status
    {
        return Status::Pending;
    }

    public function markPending(): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'markPending');
    }

    public function complete(Outcome $outcome): StepState
    {
        return new CompletedState($this->startedAt, new DateTimeImmutable(), $outcome);
    }

    public function fail(Outcome $outcome): StepState
    {
        return new FailedState($this->startedAt, new DateTimeImmutable(), $outcome);
    }

    public function outcome(): ?Outcome
    {
        return null;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
