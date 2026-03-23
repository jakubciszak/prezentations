<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

/** Communication - a phase of the onboarding process containing Steps (Activities) */
final class Stage
{
    private Status $status;

    /** @var array<string, Step> */
    private array $steps = [];

    private ?string $currentStepId = null;

    public function __construct(
        public readonly string $stageId,
        public readonly string $name,
    ) {
        $this->status = Status::Initialized;
    }

    public function addStep(Step $step): void
    {
        $this->steps[$step->stepId] = $step;
    }

    public function startStep(string $stepId): Step
    {
        $step = $this->steps[$stepId] ?? throw new \InvalidArgumentException("Step {$stepId} not found in stage {$this->stageId}");
        $step->markPending();
        $this->currentStepId = $stepId;

        if ($this->status === Status::Initialized) {
            $this->status = Status::Pending;
        }

        return $step;
    }

    public function currentStep(): ?Step
    {
        return $this->currentStepId ? $this->steps[$this->currentStepId] : null;
    }

    public function getStep(string $stepId): ?Step
    {
        return $this->steps[$stepId] ?? null;
    }

    public function markCompleted(): void
    {
        $this->status = Status::Completed;
    }

    public function status(): Status
    {
        return $this->status;
    }

    /** @return array<string, Step> */
    public function steps(): array
    {
        return $this->steps;
    }
}
