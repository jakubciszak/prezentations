<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
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
