<?php

declare(strict_types=1);

namespace App\Membership\Template;

/**
 * Declarative definition of a single step (Activity) within a stage.
 * Loaded from YAML configuration.
 */
final readonly class StepDefinition
{
    /**
     * @param array<string, array{next_step?: string, terminal?: bool, case_outcome?: string}> $outcomes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $service,
        public string $action,
        public array $outcomes,
        public array $requiredMetadata = [],
        public array $participants = [],
    ) {}
}
