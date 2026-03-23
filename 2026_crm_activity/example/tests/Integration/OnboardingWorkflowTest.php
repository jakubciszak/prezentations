<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\ExternalService\DocumentServiceStub;
use App\ExternalService\ExternalServiceResponse;
use App\ExternalService\KucServiceStub;
use App\ExternalService\ProspectFormServiceStub;
use App\ExternalService\RiskCalculationServiceStub;
use App\ExternalService\ServiceDispatcher;
use App\Infrastructure\YamlTemplateLoader;
use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Event\ActionFinished;
use App\Onboarding\Event\ActionInitialized;
use App\Onboarding\Event\CaseEvent;
use App\Onboarding\Event\CaseFinished;
use App\Onboarding\Event\CaseStarted;
use App\Onboarding\Handler\ExternalServiceResponseHandler;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Status;
use Munus\Collection\Stream;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Tests\Factory\ClientDataFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests - full onboarding workflows through engine, services, and events.
 * No mocks - uses real YAML templates, real service stubs, real Symfony Messenger bus.
 */
final class OnboardingWorkflowTest extends TestCase
{
    private OnboardingEngine $engine;

    /** @var CaseEvent[] */
    private array $recordedEvents = [];

    protected function setUp(): void
    {
        $loader = new YamlTemplateLoader(__DIR__ . '/../../config/templates');
        $registry = $loader->loadAll();

        $this->recordedEvents = [];
        $engine = null;

        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                CaseStarted::class => [fn($e) => $this->recordedEvents[] = $e],
                ActionInitialized::class => [fn($e) => $this->recordedEvents[] = $e],
                \App\Onboarding\Event\ActionPending::class => [fn($e) => $this->recordedEvents[] = $e],
                ActionFinished::class => [fn($e) => $this->recordedEvents[] = $e],
                CaseFinished::class => [fn($e) => $this->recordedEvents[] = $e],
                ExternalServiceResponse::class => [
                    function ($r) use (&$engine) {
                        (new ExternalServiceResponseHandler($engine))($r);
                    },
                ],
            ])),
        ]);

        $serviceDispatcher = new ServiceDispatcher(
            new KucServiceStub($bus),
            new DocumentServiceStub($bus),
            new ProspectFormServiceStub($bus),
            new RiskCalculationServiceStub($bus),
        );

        $engine = new OnboardingEngine($registry, $serviceDispatcher, $bus);
        $this->engine = $engine;
    }

    // --- given ---

    private function givenLowRiskStandardCase(): OnboardingCase
    {
        return $this->engine->startCase('business_standard', ClientDataFactory::lowRiskClient());
    }

    private function givenMediumRiskStandardCase(): OnboardingCase
    {
        return $this->engine->startCase('business_standard', ClientDataFactory::mediumRiskClient());
    }

    private function givenFlaggedNipCase(): OnboardingCase
    {
        return $this->engine->startCase('business_standard', ClientDataFactory::flaggedNipClient());
    }

    private function givenUnknownNipCase(): OnboardingCase
    {
        return $this->engine->startCase('business_standard', ClientDataFactory::unknownNipClient());
    }

    private function givenSimplifiedPartnerCase(): OnboardingCase
    {
        return $this->engine->startCase('business_simplified', ClientDataFactory::simplifiedPartner());
    }

    // --- then: case status ---

    private function thenCaseIsCompletedWith(OnboardingCase $case, CaseOutcome $expectedOutcome): void
    {
        self::assertSame(Status::Completed, $case->status());
        self::assertSame($expectedOutcome, $case->caseOutcome());
    }

    // --- then: events ---

    /**
     * @return string[]
     */
    private function thenStepsInitializedInOrder(): array
    {
        return Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();
    }

    private function thenEventFlowStartsAndFinishes(OnboardingCase $case): void
    {
        $eventTypes = Stream::ofAll($this->recordedEvents)
            ->map(fn(CaseEvent $e) => $e::class)
            ->toArray();

        self::assertSame(CaseStarted::class, $eventTypes[0]);
        self::assertSame(CaseFinished::class, end($eventTypes));
    }

    private function thenAllEventsHaveCaseId(OnboardingCase $case): void
    {
        foreach ($this->recordedEvents as $event) {
            self::assertSame($case->id->value, $event->caseId());
            self::assertNotNull($event->occurredAt());
        }
    }

    private function thenTerminalEventHasOutcome(string $expectedOutcome): void
    {
        $terminalFinished = Stream::ofAll($this->recordedEvents)
            ->find(fn($e) => $e instanceof ActionFinished && $e->isTerminal);

        self::assertTrue($terminalFinished->isPresent());
        self::assertSame($expectedOutcome, $terminalFinished->get()->caseOutcome);
    }

    // --- then: stages ---

    /**
     * @return array<string, Status>
     */
    private function thenAllStagesAreCompleted(OnboardingCase $case): void
    {
        $case->stages()->forEach(function ($stage) {
            self::assertSame(
                Status::Completed,
                $stage->status(),
                "Stage '{$stage->name}' should be completed but is {$stage->status()->value}",
            );
        });
    }

    // --- Standard onboarding: Happy path (low risk) ---

    #[Test]
    public function low_risk_client_flows_through_to_approval(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Approved);
        $this->thenEventFlowStartsAndFinishes($case);

        self::assertSame([
            'collect_prospect_data',
            'check_kuc',
            'calculate_risk',
            'collect_basic_documents',
            'final_approval',
        ], $this->thenStepsInitializedInOrder());
    }

    // --- Standard onboarding: Medium risk ---

    #[Test]
    public function medium_risk_client_gets_extended_documents(): void
    {
        $case = $this->givenMediumRiskStandardCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Approved);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('collect_extended_documents', $steps);
        self::assertNotContains('collect_basic_documents', $steps);
    }

    // --- Standard onboarding: Flagged NIP ---

    #[Test]
    public function flagged_nip_triggers_manual_review_then_continues(): void
    {
        $case = $this->givenFlaggedNipCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Approved);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('manual_kuc_review', $steps);

        $kucIdx = array_search('check_kuc', $steps);
        $manualIdx = array_search('manual_kuc_review', $steps);
        $riskIdx = array_search('calculate_risk', $steps);
        self::assertLessThan($manualIdx, $kucIdx);
        self::assertLessThan($riskIdx, $manualIdx);
    }

    // --- Standard onboarding: NIP not found → rejected ---

    #[Test]
    public function nip_not_found_rejects_case(): void
    {
        $case = $this->givenUnknownNipCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame(
            ['collect_prospect_data', 'check_kuc'],
            $this->thenStepsInitializedInOrder(),
        );

        $this->thenTerminalEventHasOutcome('rejected');
    }

    // --- Simplified onboarding ---

    #[Test]
    public function simplified_partner_onboarding_completes(): void
    {
        $case = $this->givenSimplifiedPartnerCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Approved);

        self::assertSame([
            'collect_prospect_data',
            'quick_risk_check',
            'collect_minimal_documents',
            'auto_approve',
        ], $this->thenStepsInitializedInOrder());
    }

    // --- Stage transitions ---

    #[Test]
    public function stages_are_properly_completed_during_workflow(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $this->thenAllStagesAreCompleted($case);
    }

    // --- Event audit trail ---

    #[Test]
    public function all_events_have_case_id_and_timestamp(): void
    {
        $case = $this->givenLowRiskStandardCase();

        self::assertNotEmpty($this->recordedEvents);
        $this->thenAllEventsHaveCaseId($case);
    }

    // --- Engine case retrieval ---

    #[Test]
    public function engine_stores_and_retrieves_cases(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $retrieved = $this->engine->getCase($case->id->value);
        self::assertSame($case, $retrieved);
    }

    #[Test]
    public function engine_returns_null_for_unknown_case(): void
    {
        self::assertNull($this->engine->getCase('nonexistent'));
    }
}
