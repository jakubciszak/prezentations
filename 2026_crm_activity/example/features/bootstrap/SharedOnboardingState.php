<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Handler\CaseEventLogger;
use App\Onboarding\Model\OnboardingCase;

/**
 * Shared state between Behat contexts.
 *
 * Behat creates separate context instances per scenario but they can share
 * state via a singleton holder. Each context accesses the same engine, case, etc.
 */
final class SharedOnboardingState
{
    private static ?self $instance = null;

    public OnboardingEngine $engine;
    public CaseEventLogger $eventLogger;
    public ?OnboardingCase $currentCase = null;
    public array $clientData = [];

    /** @var string[] step IDs that were initialized */
    public array $initializedSteps = [];

    /** @var array<string, string> step ID → outcome value */
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
