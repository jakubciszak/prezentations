<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Onboarding\Engine\TransitionResolver;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Template\StepDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransitionResolverTest extends TestCase
{
    private TransitionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TransitionResolver();
    }

    private function stepWithOutcomes(array $outcomes): StepDefinition
    {
        return new StepDefinition(
            id: 'test_step',
            name: 'Test Step',
            service: 'test_service',
            action: 'test_action',
            outcomes: $outcomes,
        );
    }

    #[Test]
    public function resolves_next_step_transition(): void
    {
        $step = $this->stepWithOutcomes([
            'clean' => ['next_step' => 'calculate_risk'],
            'flagged' => ['next_step' => 'manual_review'],
        ]);

        $result = $this->resolver->resolve($step, new Outcome('clean'));

        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertFalse($transition->isTerminal);
        self::assertSame('calculate_risk', $transition->nextStepId->value);
        self::assertNull($transition->caseOutcome);
    }

    #[Test]
    public function resolves_terminal_transition(): void
    {
        $step = $this->stepWithOutcomes([
            'approved' => ['terminal' => true, 'case_outcome' => 'approved'],
            'rejected' => ['terminal' => true, 'case_outcome' => 'rejected'],
        ]);

        $result = $this->resolver->resolve($step, new Outcome('rejected'));

        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertTrue($transition->isTerminal);
        self::assertSame(CaseOutcome::Rejected, $transition->caseOutcome);
        self::assertNull($transition->nextStepId);
    }

    #[Test]
    public function returns_none_for_unknown_outcome(): void
    {
        $step = $this->stepWithOutcomes([
            'clean' => ['next_step' => 'next'],
        ]);

        $result = $this->resolver->resolve($step, new Outcome('unknown'));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function resolves_correct_branch_among_multiple(): void
    {
        $step = $this->stepWithOutcomes([
            'low_risk' => ['next_step' => 'basic_docs'],
            'medium_risk' => ['next_step' => 'extended_docs'],
            'high_risk' => ['terminal' => true, 'case_outcome' => 'rejected'],
        ]);

        $low = $this->resolver->resolve($step, new Outcome('low_risk'));
        $medium = $this->resolver->resolve($step, new Outcome('medium_risk'));
        $high = $this->resolver->resolve($step, new Outcome('high_risk'));

        self::assertSame('basic_docs', $low->get()->nextStepId->value);
        self::assertSame('extended_docs', $medium->get()->nextStepId->value);
        self::assertTrue($high->get()->isTerminal);
        self::assertSame(CaseOutcome::Rejected, $high->get()->caseOutcome);
    }
}
