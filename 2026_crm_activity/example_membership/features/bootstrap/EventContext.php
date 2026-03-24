<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Step\Then;

final class EventContext implements Context
{
    private SharedMembershipState $state;

    public function __construct()
    {
        $this->state = SharedMembershipState::getInstance();
    }

    #[Then('the event log should contain :fragment')]
    public function theEventLogShouldContain(string $fragment): void
    {
        $found = false;
        foreach ($this->state->eventLogger->getLog() as $line) {
            if (str_contains($line, $fragment)) {
                $found = true;
                break;
            }
        }

        assert($found, "Event log does not contain '{$fragment}'");
    }
}
