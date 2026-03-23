<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

/**
 * Failed state - step ended with a failure outcome.
 * Terminal state - no further transitions allowed.
 */
final readonly class FailedState implements StepState
{
    public function __construct(
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $completedAt,
        private Outcome $outcome,
    ) {}

    public function status(): Status
    {
        return Status::Failed;
    }

    public function markPending(): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'markPending');
    }

    public function complete(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'complete');
    }

    public function fail(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'fail');
    }

    public function outcome(): Outcome
    {
        return $this->outcome;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
