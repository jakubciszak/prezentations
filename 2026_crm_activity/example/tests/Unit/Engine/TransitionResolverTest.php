<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Onboarding\Engine\TransitionResolver;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\Transition;
use App\Onboarding\Template\StepDefinition;
use Munus\Control\Option;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\StepDefinitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransitionResolverTest extends TestCase
{
    private TransitionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TransitionResolver();
    }

    // --- given ---

    private function givenStepWithBranchingOutcomes(): StepDefinition
    {
        return StepDefinitionBuilder::aStepDefinition()
            ->withNextStepOutcome('clean', 'calculate_risk')
            ->withNextStepOutcome('flagged', 'manual_review')
            ->build();
    }

    private function givenStepWithTerminalOutcomes(): StepDefinition
    {
        return StepDefinitionBuilder::aStepDefinition()
            ->withTerminalOutcome('approved', 'approved')
            ->withTerminalOutcome('rejected', 'rejected')
            ->build();
    }

    private function givenStepWithRiskOutcomes(): StepDefinition
    {
        return StepDefinitionBuilder::aStepDefinition()
            ->withId('calculate_risk')
            ->withNextStepOutcome('low_risk', 'basic_docs')
            ->withNextStepOutcome('medium_risk', 'extended_docs')
            ->withTerminalOutcome('high_risk', 'rejected')
            ->build();
    }

    // --- when ---

    private function whenResolvingOutcome(StepDefinition $step, string $outcomeValue): Option
    {
        return $this->resolver->resolve($step, OutcomeFactory::withValue($outcomeValue));
    }

    // --- then ---

    private function thenTransitionGoesToStep(Option $result, string $expectedStepId): void
    {
        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertFalse($transition->isTerminal);
        self::assertSame($expectedStepId, $transition->nextStepId->value);
        self::assertNull($transition->caseOutcome);
    }

    private function thenTransitionIsTerminal(Option $result, CaseOutcome $expectedOutcome): void
    {
        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertTrue($transition->isTerminal);
        self::assertSame($expectedOutcome, $transition->caseOutcome);
        self::assertNull($transition->nextStepId);
    }

    private function thenNoTransitionFound(Option $result): void
    {
        self::assertTrue($result->isEmpty());
    }

    // --- tests ---

    #[Test]
    public function resolves_next_step_transition(): void
    {
        $step = $this->givenStepWithBranchingOutcomes();

        $result = $this->whenResolvingOutcome($step, 'clean');

        $this->thenTransitionGoesToStep($result, 'calculate_risk');
    }

    #[Test]
    public function resolves_terminal_transition(): void
    {
        $step = $this->givenStepWithTerminalOutcomes();

        $result = $this->whenResolvingOutcome($step, 'rejected');

        $this->thenTransitionIsTerminal($result, CaseOutcome::Rejected);
    }

    #[Test]
    public function returns_none_for_unknown_outcome(): void
    {
        $step = $this->givenStepWithBranchingOutcomes();

        $result = $this->whenResolvingOutcome($step, 'unknown');

        $this->thenNoTransitionFound($result);
    }

    #[Test]
    public function resolves_correct_branch_among_multiple(): void
    {
        $step = $this->givenStepWithRiskOutcomes();

        $low = $this->whenResolvingOutcome($step, 'low_risk');
        $medium = $this->whenResolvingOutcome($step, 'medium_risk');
        $high = $this->whenResolvingOutcome($step, 'high_risk');

        $this->thenTransitionGoesToStep($low, 'basic_docs');
        $this->thenTransitionGoesToStep($medium, 'extended_docs');
        $this->thenTransitionIsTerminal($high, CaseOutcome::Rejected);
    }
}
