<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use DateTimeImmutable;

/**
 * State pattern interface for Step (Activity) lifecycle.
 *
 * Each state defines which transitions are allowed and what behavior
 * is available. Invalid transitions throw IllegalStateTransitionException.
 */
interface StepState
{
    public function status(): Status;

    /** Transition to Pending - dispatched to external service */
    public function markPending(): StepState;

    /** Transition to Completed with a positive outcome */
    public function complete(Outcome $outcome): StepState;

    /** Transition to Failed with a negative outcome */
    public function fail(Outcome $outcome): StepState;

    public function outcome(): ?Outcome;

    public function startedAt(): ?DateTimeImmutable;

    public function completedAt(): ?DateTimeImmutable;
}
