<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Onboarding\Model\Stage;
use App\Onboarding\Model\StageId;
use App\Onboarding\Model\Step;
use App\Onboarding\Model\StepId;

final class StageBuilder
{
    private string $id = 'verification';
    private string $name = 'Client Verification';

    /** @var Step[] */
    private array $steps = [];

    private ?string $startStepId = null;

    public static function aStage(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withStep(Step $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function withSteps(Step ...$steps): self
    {
        $this->steps = array_merge($this->steps, $steps);
        return $this;
    }

    public function withStartedStep(string $stepId): self
    {
        $this->startStepId = $stepId;
        return $this;
    }

    public function build(): Stage
    {
        $stage = new Stage(new StageId($this->id), $this->name);

        foreach ($this->steps as $step) {
            $stage->addStep($step);
        }

        if ($this->startStepId !== null) {
            $stage->startStep(new StepId($this->startStepId));
        }

        return $stage;
    }
}
