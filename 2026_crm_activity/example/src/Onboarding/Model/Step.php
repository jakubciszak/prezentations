<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

use App\Onboarding\Model\State\InitializedState;
use App\Onboarding\Model\State\StepState;
use DateTimeImmutable;

final class Step
{
    private StepState $state;

    public function __construct(
        public readonly StepId $stepId,
        public readonly string $name,
        public readonly ServiceAction $serviceAction,
    ) {
        $this->state = new InitializedState();
    }

    public function markPending(): void
    {
        $this->state = $this->state->markPending();
    }

    public function complete(Outcome $outcome): void
    {
        $this->state = $this->state->complete($outcome);
    }

    public function fail(Outcome $outcome): void
    {
        $this->state = $this->state->fail($outcome);
    }

    public function status(): Status
    {
        return $this->state->status;
    }

    public function outcome(): ?Outcome
    {
        return $this->state->outcome;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->state->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->state->completedAt;
    }
}
