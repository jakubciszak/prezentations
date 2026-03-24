<?php

declare(strict_types=1);

namespace App\Membership\Model\State;

use App\Membership\Model\Outcome;
use App\Membership\Model\Status;
use DateTimeImmutable;

final readonly class FailedState implements StepState
{
    public Status $status;

    public function __construct(
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public Outcome $outcome,
    ) {
        $this->status = Status::Failed;
    }

    public function markPending(): StepState
    {
        throw IllegalStateTransitionException::create($this->status, 'markPending');
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
