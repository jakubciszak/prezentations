<?php

declare(strict_types=1);

namespace App\Membership\Model;

use Munus\Collection\Stream;
use Munus\Control\Option;

/** Communication - a phase of the loyalty activity processing containing Steps (Activities) */
final class Stage
{
    private Status $status;

    /** @var array<string, Step> */
    private array $steps = [];

    private ?StepId $currentStepId = null;

    public function __construct(
        public readonly StageId $stageId,
        public readonly string $name,
    ) {
        $this->status = Status::Initialized;
    }

    public function addStep(Step $step): void
    {
        $this->steps[$step->stepId->value] = $step;
    }

    public function startStep(StepId $stepId): Step
    {
        $step = $this->getStep($stepId)
            ->getOrElseThrow(new \InvalidArgumentException("Step {$stepId} not found in stage {$this->stageId}"));

        $step->markPending();
        $this->currentStepId = $stepId;

        if ($this->status === Status::Initialized) {
            $this->status = Status::Pending;
        }

        return $step;
    }

    public function currentStep(): ?Step
    {
        return $this->currentStepId
            ? $this->getStep($this->currentStepId)->getOrNull()
            : null;
    }

    /**
     * @return Option<Step>
     */
    public function getStep(StepId $stepId): Option
    {
        return Option::of($this->steps[$stepId->value] ?? null);
    }

    public function hasStep(StepId $stepId): bool
    {
        return isset($this->steps[$stepId->value]);
    }

    public function markCompleted(): void
    {
        $this->status = Status::Completed;
    }

    public function status(): Status
    {
        return $this->status;
    }

    /**
     * @return Stream<Step>
     */
    public function steps(): Stream
    {
        return Stream::ofAll(array_values($this->steps));
    }
}
