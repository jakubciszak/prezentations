<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Kernel;
use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Handler\CaseEventLogger;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

final class OnboardingContext implements Context
{
    private SharedOnboardingState $state;
    private string $clientType;

    public function __construct()
    {
        $this->state = SharedOnboardingState::getInstance();
    }

    #[BeforeScenario]
    public function resetState(BeforeScenarioScope $scope): void
    {
        SharedOnboardingState::reset();
        $this->state = SharedOnboardingState::getInstance();
    }

    #[Given('the :clientType onboarding template is loaded')]
    public function theOnboardingTemplateIsLoaded(string $clientType): void
    {
        $this->clientType = $clientType;

        $kernel = new Kernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->state->engine = $container->get(OnboardingEngine::class);
        $this->state->eventLogger = $container->get(CaseEventLogger::class);
    }

    #[Given('a client :companyName with NIP :nip')]
    public function aClientWithNip(string $companyName, string $nip): void
    {
        $this->state->clientData = [
            'company_name' => $companyName,
            'nip' => $nip,
            'contact_email' => 'test@example.pl',
        ];
    }

    #[Given('the client has annual revenue of :revenue PLN')]
    public function theClientHasAnnualRevenueOf(int $revenue): void
    {
        $this->state->clientData['annual_revenue'] = $revenue;
    }

    #[Given('the client has partner referral code :code')]
    public function theClientHasPartnerReferralCode(string $code): void
    {
        $this->state->clientData['partner_referral_code'] = $code;
    }

    #[When('the onboarding case is started')]
    public function theOnboardingCaseIsStarted(): void
    {
        $this->state->currentCase = $this->state->engine->startCase(
            $this->clientType,
            $this->state->clientData,
        );

        $this->parseEventLog();
    }

    #[Then('the case should be completed with outcome :outcome')]
    public function theCaseShouldBeCompletedWithOutcome(string $outcome): void
    {
        $case = $this->state->currentCase;
        assert($case !== null, 'No case started');
        assert($case->status()->value === 'completed', "Case status is '{$case->status()->value}', expected 'completed'");
        assert($case->caseOutcome()?->value === $outcome, "Case outcome is '{$case->caseOutcome()?->value}', expected '{$outcome}'");
    }

    #[Then('the following steps should have been executed in order:')]
    public function theFollowingStepsShouldHaveBeenExecutedInOrder(TableNode $table): void
    {
        $expected = array_column($table->getHash(), 'step');
        $actual = $this->state->initializedSteps;

        assert($actual === $expected, sprintf(
            "Steps order mismatch.\nExpected: %s\nActual:   %s",
            implode(' → ', $expected),
            implode(' → ', $actual),
        ));
    }

    #[Then('the step :stepId should have been executed')]
    public function theStepShouldHaveBeenExecuted(string $stepId): void
    {
        assert(
            in_array($stepId, $this->state->initializedSteps, true),
            "Step '{$stepId}' was not executed. Executed steps: " . implode(', ', $this->state->initializedSteps),
        );
    }

    #[Then('the step :stepId should not have been executed')]
    public function theStepShouldNotHaveBeenExecuted(string $stepId): void
    {
        assert(
            !in_array($stepId, $this->state->initializedSteps, true),
            "Step '{$stepId}' was executed but should not have been",
        );
    }

    private function parseEventLog(): void
    {
        $this->state->initializedSteps = [];
        $this->state->stepOutcomes = [];

        foreach ($this->state->eventLogger->getLog() as $line) {
            if (str_contains($line, '[ACTION INIT]') && preg_match('/step=(\S+)/', $line, $m)) {
                $this->state->initializedSteps[] = $m[1];
            }
            if (str_contains($line, '[ACTION DONE]') && preg_match('/step=(\S+)\s+outcome=(\S+)/', $line, $m)) {
                $this->state->stepOutcomes[$m[1]] = $m[2];
            }
        }
    }
}
