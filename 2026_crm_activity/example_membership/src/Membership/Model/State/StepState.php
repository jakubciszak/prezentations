<?php

declare(strict_types=1);

namespace App\Membership\Model\State;

use App\Membership\Model\Outcome;
use App\Membership\Model\Status;
use DateTimeImmutable;

interface StepState
{
    public Status $status { get; }
    public ?Outcome $outcome { get; }
    public ?DateTimeImmutable $startedAt { get; }
    public ?DateTimeImmutable $completedAt { get; }

    public function markPending(): StepState;
    public function complete(Outcome $outcome): StepState;
    public function fail(Outcome $outcome): StepState;
}
