<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Step\Then;

final class PointsContext implements Context
{
    private SharedMembershipState $state;

    public function __construct()
    {
        $this->state = SharedMembershipState::getInstance();
    }

    #[Then('the step :stepId should have produced outcome :outcome')]
    public function theStepShouldHaveProducedOutcome(string $stepId, string $outcome): void
    {
        $actual = $this->state->stepOutcomes[$stepId] ?? null;

        assert($actual !== null, "Step '{$stepId}' has no recorded outcome. Was it executed?");
        assert($actual === $outcome, "Step '{$stepId}' outcome is '{$actual}', expected '{$outcome}'");
    }
}
