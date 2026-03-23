<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

final readonly class InitializedState implements StepState
{
    public Status $status;
    public ?Outcome $outcome;
    public ?DateTimeImmutable $startedAt;
    public ?DateTimeImmutable $completedAt;

    public function __construct()
    {
        $this->status = Status::Initialized;
        $this->outcome = null;
        $this->startedAt = null;
        $this->completedAt = null;
    }

    public function markPending(): StepState
    {
        return new PendingState(new DateTimeImmutable());
    }

    public function complete(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status, 'complete');
    }

    public function fail(Outcome $outcome): StepState
    {
        throw IllegalStateTransitionException::create($this->status, 'fail');
    }
}
