<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

final readonly class PendingState implements StepState
{
    public Status $status;
    public ?Outcome $outcome;
    public ?DateTimeImmutable $completedAt;

    public function __construct(
        public DateTimeImmutable $startedAt,
    ) {
        $this->status = Status::Pending;
        $this->outcome = null;
        $this->completedAt = null;
    }

    public function markPending(): StepState
    {
        throw IllegalStateTransitionException::create($this->status, 'markPending');
    }

    public function complete(Outcome $outcome): StepState
    {
        return new CompletedState($this->startedAt, new DateTimeImmutable(), $outcome);
    }

    public function fail(Outcome $outcome): StepState
    {
        return new FailedState($this->startedAt, new DateTimeImmutable(), $outcome);
    }
}
