<?php

declare(strict_types=1);

namespace App\Membership\Template;

/**
 * Immutable template defining a loyalty activity flow.
 * Built from YAML configuration - purely declarative.
 */
final readonly class ActivityTemplate
{
    /**
     * @param StageDefinition[] $stages
     */
    public function __construct(
        public string $name,
        public string $activityType,
        public int $version,
        public string $description,
        public array $stages,
    ) {}

    public function findStep(string $stepId): StepDefinition
    {
        foreach ($this->stages as $stage) {
            foreach ($stage->steps as $step) {
                if ($step->id === $stepId) {
                    return $step;
                }
            }
        }
        throw new \InvalidArgumentException("Step '{$stepId}' not found in template '{$this->name}'");
    }

    public function findStageForStep(string $stepId): StageDefinition
    {
        foreach ($this->stages as $stage) {
            foreach ($stage->steps as $step) {
                if ($step->id === $stepId) {
                    return $stage;
                }
            }
        }
        throw new \InvalidArgumentException("Stage for step '{$stepId}' not found in template '{$this->name}'");
    }

    public function firstStepId(): string
    {
        return $this->stages[0]->steps[0]->id;
    }
}
