<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

/**
 * Initial state - step has been created but not yet dispatched.
 * Only transition allowed: markPending()
 */
final readonly class InitializedState implements StepState
{
    public function status(): Status
    {
        return Status::Initialized;
    }

    public function markPending(): StepState
    {
        return new PendingState(new DateTimeImmutable());
    }

    public function complete(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'complete');
    }

    public function fail(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status(), 'fail');
    }

    public function outcome(): ?Outcome
    {
        return null;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return null;
    }
}
