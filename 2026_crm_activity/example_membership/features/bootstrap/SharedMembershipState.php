<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Membership\Engine\MembershipEngine;
use App\Membership\Handler\CaseEventLogger;
use App\Membership\Model\CaseRepository;
use App\Membership\Model\MembershipCase;

final class SharedMembershipState
{
    private static ?self $instance = null;

    public MembershipEngine $engine;
    public CaseEventLogger $eventLogger;
    public CaseRepository $caseRepository;
    public ?MembershipCase $currentCase = null;
    public array $activityData = [];

    /** @var string[] */
    public array $initializedSteps = [];

    /** @var array<string, string> */
    public array $stepOutcomes = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
