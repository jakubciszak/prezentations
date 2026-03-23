<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Onboarding\Model\Status;
use Behat\Behat\Context\Context;

/**
 * Context for stage progression and document collection assertions.
 */
final class DocumentContext implements Context
{
    private SharedOnboardingState $state;

    public function __construct()
    {
        $this->state = SharedOnboardingState::getInstance();
    }

    /**
     * @Then all stages should be completed
     */
    public function allStagesShouldBeCompleted(): void
    {
        $case = $this->state->currentCase;
        assert($case !== null);

        $case->stages()->forEach(function ($stage) {
            assert(
                $stage->status() === Status::Completed,
                "Stage '{$stage->name}' is '{$stage->status()->value}', expected 'completed'",
            );
        });
    }

    /**
     * @Then the stage :stageName should be completed
     */
    public function theStageShouldBeCompleted(string $stageName): void
    {
        $found = false;
        $this->state->currentCase->stages()->forEach(function ($stage) use ($stageName, &$found) {
            if ($stage->name === $stageName) {
                $found = true;
                assert(
                    $stage->status() === Status::Completed,
                    "Stage '{$stageName}' is '{$stage->status()->value}', expected 'completed'",
                );
            }
        });

        assert($found, "Stage '{$stageName}' not found");
    }

    /**
     * @Then the stage :stageName should not be completed
     */
    public function theStageShouldNotBeCompleted(string $stageName): void
    {
        $this->state->currentCase->stages()->forEach(function ($stage) use ($stageName) {
            if ($stage->name === $stageName) {
                assert(
                    $stage->status() !== Status::Completed,
                    "Stage '{$stageName}' is completed but should not be",
                );
            }
        });
    }
}
