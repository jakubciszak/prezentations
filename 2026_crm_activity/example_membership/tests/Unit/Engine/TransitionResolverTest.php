<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Membership\Engine\TransitionResolver;
use App\Membership\Model\CaseOutcome;
use App\Membership\Model\Outcome;
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

    #[Test]
    public function resolves_next_step_transition(): void
    {
        $stepDef = StepDefinitionBuilder::aStepDefinition()
            ->withNextStepOutcome('valid', 'calculate_points')
            ->withTerminalOutcome('invalid', 'rejected')
            ->build();

        $result = $this->resolver->resolve($stepDef, new Outcome('valid'));

        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertFalse($transition->isTerminal);
        self::assertSame('calculate_points', $transition->nextStepId->value);
    }

    #[Test]
    public function resolves_terminal_transition(): void
    {
        $stepDef = StepDefinitionBuilder::aStepDefinition()
            ->withNextStepOutcome('valid', 'calculate_points')
            ->withTerminalOutcome('invalid', 'rejected')
            ->build();

        $result = $this->resolver->resolve($stepDef, new Outcome('invalid'));

        self::assertTrue($result->isPresent());
        $transition = $result->get();
        self::assertTrue($transition->isTerminal);
        self::assertSame(CaseOutcome::Rejected, $transition->caseOutcome);
    }

    #[Test]
    public function returns_none_for_unknown_outcome(): void
    {
        $stepDef = StepDefinitionBuilder::aStepDefinition()
            ->withNextStepOutcome('valid', 'calculate_points')
            ->build();

        $result = $this->resolver->resolve($stepDef, new Outcome('unknown'));

        self::assertFalse($result->isPresent());
    }
}
