<?php

declare(strict_types=1);

namespace App\Membership\Template;

/**
 * Declarative definition of a stage (Communication) containing steps.
 * Loaded from YAML configuration.
 */
final readonly class StageDefinition
{
    /**
     * @param StepDefinition[] $steps
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $steps,
    ) {}
}
