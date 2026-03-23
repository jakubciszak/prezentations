<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Onboarding\Template\StepDefinition;

final class StepDefinitionBuilder
{
    private string $id = 'test_step';
    private string $name = 'Test Step';
    private string $service = 'test_service';
    private string $action = 'test_action';
    private array $outcomes = [];

    public static function aStepDefinition(): self
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

    public function withService(string $service, string $action): self
    {
        $this->service = $service;
        $this->action = $action;
        return $this;
    }

    public function withOutcome(string $name, ?string $nextStep = null, bool $terminal = false, ?string $caseOutcome = null): self
    {
        $config = [];
        if ($nextStep !== null) {
            $config['next_step'] = $nextStep;
        }
        if ($terminal) {
            $config['terminal'] = true;
        }
        if ($caseOutcome !== null) {
            $config['case_outcome'] = $caseOutcome;
        }
        $this->outcomes[$name] = $config;

        return $this;
    }

    public function withNextStepOutcome(string $outcomeName, string $nextStep): self
    {
        return $this->withOutcome($outcomeName, nextStep: $nextStep);
    }

    public function withTerminalOutcome(string $outcomeName, string $caseOutcome): self
    {
        return $this->withOutcome($outcomeName, terminal: true, caseOutcome: $caseOutcome);
    }

    public function build(): StepDefinition
    {
        return new StepDefinition(
            id: $this->id,
            name: $this->name,
            service: $this->service,
            action: $this->action,
            outcomes: $this->outcomes,
        );
    }
}
