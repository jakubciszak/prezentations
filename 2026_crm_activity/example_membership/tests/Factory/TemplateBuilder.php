<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Membership\Template\ActivityTemplate;
use App\Membership\Template\StageDefinition;
use App\Membership\Template\StepDefinition;

final class TemplateBuilder
{
    private string $name = 'Test Template';
    private string $activityType = 'test';
    private int $version = 1;
    private string $description = 'Test template';

    /** @var StageDefinition[] */
    private array $stages = [];

    public static function aTemplate(): self
    {
        return new self();
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withActivityType(string $activityType): self
    {
        $this->activityType = $activityType;
        return $this;
    }

    /**
     * @param StepDefinition[] $steps
     */
    public function withStage(string $id, string $name, array $steps): self
    {
        $this->stages[] = new StageDefinition($id, $name, $steps);
        return $this;
    }

    /**
     * Build a simple 2-step template: validate → calculate_points → terminal
     */
    public static function twoStepTemplate(): ActivityTemplate
    {
        return self::aTemplate()
            ->withStage('validation', 'Validation', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('validate_transaction')
                    ->withName('Validate Transaction')
                    ->withService('transaction', 'validate')
                    ->withNextStepOutcome('valid', 'calculate_points')
                    ->withTerminalOutcome('invalid', 'rejected')
                    ->build(),
            ])
            ->withStage('points', 'Points Processing', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('calculate_points')
                    ->withName('Calculate Points')
                    ->withService('points', 'calculate')
                    ->withTerminalOutcome('points_calculated', 'points_awarded')
                    ->withTerminalOutcome('zero_points', 'completed_no_points')
                    ->build(),
            ])
            ->build();
    }

    /**
     * Build a branching template: validate → points → tier evaluation with upgrade path
     */
    public static function branchingTemplate(): ActivityTemplate
    {
        return self::aTemplate()
            ->withName('Branching Template')
            ->withActivityType('branching')
            ->withStage('validation', 'Validation', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('validate_transaction')
                    ->withName('Validate Transaction')
                    ->withService('transaction', 'validate')
                    ->withNextStepOutcome('valid', 'calculate_points')
                    ->withTerminalOutcome('invalid', 'rejected')
                    ->build(),
            ])
            ->withStage('points', 'Points Processing', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('calculate_points')
                    ->withName('Calculate Points')
                    ->withService('points', 'calculate')
                    ->withNextStepOutcome('points_calculated', 'evaluate_tier')
                    ->withTerminalOutcome('zero_points', 'completed_no_points')
                    ->build(),
            ])
            ->withStage('tier', 'Tier Evaluation', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('evaluate_tier')
                    ->withName('Evaluate Tier')
                    ->withService('tier', 'evaluate')
                    ->withTerminalOutcome('tier_unchanged', 'points_awarded')
                    ->withTerminalOutcome('tier_upgrade', 'tier_upgraded')
                    ->build(),
            ])
            ->build();
    }

    public function build(): ActivityTemplate
    {
        return new ActivityTemplate(
            name: $this->name,
            activityType: $this->activityType,
            version: $this->version,
            description: $this->description,
            stages: $this->stages,
        );
    }
}
