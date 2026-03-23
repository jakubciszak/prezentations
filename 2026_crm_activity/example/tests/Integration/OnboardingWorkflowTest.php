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
use App\Onboarding\Event\ActionPending;
use App\Onboarding\Event\CaseEvent;
use App\Onboarding\Event\CaseFinished;
use App\Onboarding\Event\CaseStarted;
use App\Onboarding\Handler\ExternalServiceResponseHandler;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\Status;
use Munus\Collection\Stream;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
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
                ActionPending::class => [fn($e) => $this->recordedEvents[] = $e],
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

    // --- Standard onboarding: Happy path (low risk) ---

    #[Test]
    public function low_risk_client_flows_through_to_approval(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Test Firma Sp. z o.o.',
            'nip' => '5261234567',
            'contact_email' => 'test@firma.pl',
            'annual_revenue' => 500_000,
        ]);

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());

        // Verify event flow
        $eventTypes = Stream::ofAll($this->recordedEvents)
            ->map(fn(CaseEvent $e) => $e::class)
            ->toArray();

        // Pattern: Start → [Init → Pending → Finished] × 5 steps → CaseFinished
        self::assertSame(CaseStarted::class, $eventTypes[0]);
        self::assertSame(CaseFinished::class, end($eventTypes));

        // Steps traversed: collect_prospect_data → check_kuc → calculate_risk → collect_basic_documents → final_approval
        $stepsInitialized = Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();

        self::assertSame([
            'collect_prospect_data',
            'check_kuc',
            'calculate_risk',
            'collect_basic_documents',
            'final_approval',
        ], $stepsInitialized);
    }

    // --- Standard onboarding: Medium risk ---

    #[Test]
    public function medium_risk_client_gets_extended_documents(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Średnia Corp',
            'nip' => '7891234567',
            'contact_email' => 'cfo@srednia.pl',
            'annual_revenue' => 5_000_000, // > 1M → medium_risk
        ]);

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());

        // Should go through extended documents
        $stepsInitialized = Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();

        self::assertContains('collect_extended_documents', $stepsInitialized);
        self::assertNotContains('collect_basic_documents', $stepsInitialized);
    }

    // --- Standard onboarding: Flagged NIP ---

    #[Test]
    public function flagged_nip_triggers_manual_review_then_continues(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Podejrzana Sp. z o.o.',
            'nip' => '9961234567', // starts with 99 → flagged
            'contact_email' => 'info@podejrzana.pl',
            'annual_revenue' => 200_000,
        ]);

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());

        $stepsInitialized = Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();

        // check_kuc → manual_kuc_review → calculate_risk
        self::assertContains('manual_kuc_review', $stepsInitialized);
        $kucIdx = array_search('check_kuc', $stepsInitialized);
        $manualIdx = array_search('manual_kuc_review', $stepsInitialized);
        $riskIdx = array_search('calculate_risk', $stepsInitialized);
        self::assertLessThan($manualIdx, $kucIdx);
        self::assertLessThan($riskIdx, $manualIdx);
    }

    // --- Standard onboarding: NIP not found → rejected ---

    #[Test]
    public function nip_not_found_rejects_case(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Ghost Ltd.',
            'nip' => '0061234567', // starts with 00 → not_found
            'contact_email' => 'ghost@nowhere.com',
            'annual_revenue' => 100_000,
        ]);

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Rejected, $case->caseOutcome());

        // Should only have 2 steps: collect_prospect_data → check_kuc (terminal)
        $stepsInitialized = Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();

        self::assertSame(['collect_prospect_data', 'check_kuc'], $stepsInitialized);

        // Verify terminal action finished event
        $terminalFinished = Stream::ofAll($this->recordedEvents)
            ->find(fn($e) => $e instanceof ActionFinished && $e->isTerminal);

        self::assertTrue($terminalFinished->isPresent());
        self::assertSame('rejected', $terminalFinished->get()->caseOutcome);
    }

    // --- Simplified onboarding ---

    #[Test]
    public function simplified_partner_onboarding_completes(): void
    {
        $case = $this->engine->startCase('business_simplified', [
            'company_name' => 'Partner Fintech',
            'nip' => '1234567890',
            'contact_email' => 'partner@fintech.pl',
            'partner_referral_code' => 'REF-001',
            'annual_revenue' => 300_000,
        ]);

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());

        // Simplified path: collect_prospect_data → quick_risk_check → collect_minimal_documents → auto_approve
        $stepsInitialized = Stream::ofAll($this->recordedEvents)
            ->filter(fn($e) => $e instanceof ActionInitialized)
            ->map(fn(ActionInitialized $e) => $e->stepId)
            ->toArray();

        self::assertSame([
            'collect_prospect_data',
            'quick_risk_check',
            'collect_minimal_documents',
            'auto_approve',
        ], $stepsInitialized);
    }

    // --- Stage transitions ---

    #[Test]
    public function stages_are_properly_completed_during_workflow(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Stage Test',
            'nip' => '5261234567',
            'contact_email' => 'test@test.pl',
            'annual_revenue' => 500_000,
        ]);

        $stageStatuses = [];
        $case->stages()->forEach(function ($stage) use (&$stageStatuses) {
            $stageStatuses[$stage->name] = $stage->status();
        });

        // All stages should be completed after successful flow
        self::assertSame(Status::Completed, $stageStatuses['Prospect Intake']);
        self::assertSame(Status::Completed, $stageStatuses['Client Verification']);
        self::assertSame(Status::Completed, $stageStatuses['Document Collection']);
        self::assertSame(Status::Completed, $stageStatuses['Finalization']);
    }

    // --- Event audit trail ---

    #[Test]
    public function all_events_have_case_id_and_timestamp(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Event Test',
            'nip' => '5261234567',
            'contact_email' => 'test@test.pl',
            'annual_revenue' => 500_000,
        ]);

        self::assertNotEmpty($this->recordedEvents);

        foreach ($this->recordedEvents as $event) {
            self::assertSame($case->id->value, $event->caseId());
            self::assertNotNull($event->occurredAt());
        }
    }

    // --- Engine case retrieval ---

    #[Test]
    public function engine_stores_and_retrieves_cases(): void
    {
        $case = $this->engine->startCase('business_standard', [
            'company_name' => 'Retrieval Test',
            'nip' => '5261234567',
            'contact_email' => 'test@test.pl',
            'annual_revenue' => 500_000,
        ]);

        $retrieved = $this->engine->getCase($case->id->value);
        self::assertSame($case, $retrieved);
    }

    #[Test]
    public function engine_returns_null_for_unknown_case(): void
    {
        self::assertNull($this->engine->getCase('nonexistent'));
    }
}
