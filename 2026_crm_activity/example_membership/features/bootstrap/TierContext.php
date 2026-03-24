<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Membership\Model\Status;
use Behat\Behat\Context\Context;
use Behat\Step\Then;

final class TierContext implements Context
{
    private SharedMembershipState $state;

    public function __construct()
    {
        $this->state = SharedMembershipState::getInstance();
    }

    #[Then('all stages should be completed')]
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

    #[Then('the stage :stageName should be completed')]
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

    #[Then('the stage :stageName should not be completed')]
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
