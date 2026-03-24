<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\OnboardingCase;
use Tests\Factory\ClientDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests - full onboarding workflows through the Symfony container.
 *
 * Uses KernelTestCase (via IntegrationTestCase) to boot the real DI container
 * with autowired services: engine, messenger bus, handlers, service stubs.
 */
final class OnboardingWorkflowTest extends IntegrationTestCase
{
    // --- given ---

    private function givenLowRiskStandardCase(): OnboardingCase
    {
        return $this->givenCaseStarted('business_standard', ClientDataFactory::lowRiskClient());
    }

    private function givenMediumRiskStandardCase(): OnboardingCase
    {
        return $this->givenCaseStarted('business_standard', ClientDataFactory::mediumRiskClient());
    }

    private function givenFlaggedNipCase(): OnboardingCase
    {
        return $this->givenCaseStarted('business_standard', ClientDataFactory::flaggedNipClient());
    }

    private function givenUnknownNipCase(): OnboardingCase
    {
        return $this->givenCaseStarted('business_standard', ClientDataFactory::unknownNipClient());
    }

    private function givenSimplifiedPartnerCase(): OnboardingCase
    {
        return $this->givenCaseStarted('business_simplified', ClientDataFactory::simplifiedPartner());
    }

    // --- Standard onboarding: Happy path (low risk) ---

    #[Test]
    public function low_risk_client_flows_through_to_approval(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Approved);
        $this->thenEventLogStartsWithCaseStarted();
        $this->thenEventLogEndsWithCaseFinished();

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

        $this->thenEventLogContains('TERMINAL');
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
    public function event_log_records_full_audit_trail(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $log = $this->eventLogger->getLog();
        self::assertNotEmpty($log);

        $this->thenEventLogStartsWithCaseStarted();
        $this->thenEventLogEndsWithCaseFinished();
        $this->thenEventLogContains('case=' . $case->id->value);
    }

    // --- Repository case retrieval ---

    #[Test]
    public function repository_stores_and_retrieves_cases(): void
    {
        $case = $this->givenLowRiskStandardCase();

        $retrieved = $this->caseRepository->findById($case->id->value);
        self::assertSame($case, $retrieved);
    }

    #[Test]
    public function repository_returns_null_for_unknown_case(): void
    {
        self::assertNull($this->caseRepository->findById('nonexistent'));
    }
}
