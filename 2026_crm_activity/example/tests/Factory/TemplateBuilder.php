<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Onboarding\Template\OnboardingTemplate;
use App\Onboarding\Template\StageDefinition;
use App\Onboarding\Template\StepDefinition;

final class TemplateBuilder
{
    private string $name = 'Test Template';
    private string $clientType = 'test';
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

    public function withClientType(string $clientType): self
    {
        $this->clientType = $clientType;
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
     * Build a simple 2-step template: collect → verify → terminal
     */
    public static function twoStepTemplate(): OnboardingTemplate
    {
        return self::aTemplate()
            ->withStage('intake', 'Intake', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('collect_data')
                    ->withName('Collect Data')
                    ->withService('form', 'collect')
                    ->withNextStepOutcome('completed', 'verify')
                    ->withTerminalOutcome('abandoned', 'abandoned')
                    ->build(),
            ])
            ->withStage('verification', 'Verification', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('verify')
                    ->withName('Verify')
                    ->withService('verifier', 'check')
                    ->withTerminalOutcome('passed', 'approved')
                    ->withTerminalOutcome('failed', 'rejected')
                    ->build(),
            ])
            ->build();
    }

    /**
     * Build a branching template: risk → low/high paths
     */
    public static function branchingTemplate(): OnboardingTemplate
    {
        return self::aTemplate()
            ->withName('Branching Template')
            ->withClientType('branching')
            ->withStage('risk', 'Risk Assessment', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('calculate_risk')
                    ->withName('Calculate Risk')
                    ->withService('risk', 'calculate')
                    ->withNextStepOutcome('low_risk', 'basic_docs')
                    ->withTerminalOutcome('high_risk', 'rejected')
                    ->build(),
            ])
            ->withStage('docs', 'Documents', [
                StepDefinitionBuilder::aStepDefinition()
                    ->withId('basic_docs')
                    ->withName('Basic Documents')
                    ->withService('documents', 'request')
                    ->withTerminalOutcome('all_received', 'approved')
                    ->withTerminalOutcome('expired', 'expired')
                    ->build(),
            ])
            ->build();
    }

    public function build(): OnboardingTemplate
    {
        return new OnboardingTemplate(
            name: $this->name,
            clientType: $this->clientType,
            version: $this->version,
            description: $this->description,
            stages: $this->stages,
        );
    }
}
