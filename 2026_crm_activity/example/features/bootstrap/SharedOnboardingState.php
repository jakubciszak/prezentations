<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Handler\CaseEventLogger;
use App\Onboarding\Model\CaseRepository;
use App\Onboarding\Model\OnboardingCase;

final class SharedOnboardingState
{
    private static ?self $instance = null;

    public OnboardingEngine $engine;
    public CaseEventLogger $eventLogger;
    public CaseRepository $caseRepository;
    public ?OnboardingCase $currentCase = null;
    public array $clientData = [];

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
