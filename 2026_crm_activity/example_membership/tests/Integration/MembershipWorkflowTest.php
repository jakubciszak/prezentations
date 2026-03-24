<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Membership\Model\CaseOutcome;
use App\Membership\Model\MembershipCase;
use Tests\Factory\MemberDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests - full loyalty activity workflows through the Symfony container.
 *
 * Uses KernelTestCase (via IntegrationTestCase) to boot the real DI container
 * with autowired services: engine, messenger bus, handlers, service stubs.
 */
final class MembershipWorkflowTest extends IntegrationTestCase
{
    // --- given ---

    private function givenStandardInStorePurchase(): MembershipCase
    {
        return $this->givenCaseStarted('purchase_in_store', MemberDataFactory::standardPurchase());
    }

    private function givenHighValueInStorePurchase(): MembershipCase
    {
        return $this->givenCaseStarted('purchase_in_store', MemberDataFactory::highValuePurchase());
    }

    private function givenTierUpgradePurchase(): MembershipCase
    {
        return $this->givenCaseStarted('purchase_in_store', MemberDataFactory::tierUpgradePurchase());
    }

    private function givenFraudSuspectedPurchase(): MembershipCase
    {
        return $this->givenCaseStarted('purchase_in_store', MemberDataFactory::fraudSuspectedPurchase());
    }

    private function givenInvalidPurchase(): MembershipCase
    {
        return $this->givenCaseStarted('purchase_in_store', MemberDataFactory::invalidPurchase());
    }

    private function givenOnlinePurchase(): MembershipCase
    {
        return $this->givenCaseStarted('online_purchase', MemberDataFactory::onlinePurchase());
    }

    private function givenOnlineTierUpgradePurchase(): MembershipCase
    {
        return $this->givenCaseStarted('online_purchase', MemberDataFactory::onlineTierUpgradePurchase());
    }

    private function givenValidReferral(): MembershipCase
    {
        return $this->givenCaseStarted('referral_program', MemberDataFactory::validReferral());
    }

    private function givenDuplicateReferral(): MembershipCase
    {
        return $this->givenCaseStarted('referral_program', MemberDataFactory::duplicateReferral());
    }

    // --- In-store purchase: Happy path (standard purchase, no tier change) ---

    #[Test]
    public function standard_purchase_awards_points_with_no_reward(): void
    {
        $case = $this->givenStandardInStorePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);
        $this->thenEventLogStartsWithCaseStarted();
        $this->thenEventLogEndsWithCaseFinished();

        self::assertSame([
            'validate_transaction',
            'calculate_points',
            'evaluate_tier',
            'assign_reward',
        ], $this->thenStepsInitializedInOrder());
    }

    // --- In-store purchase: High value with reward ---

    #[Test]
    public function high_value_purchase_assigns_reward(): void
    {
        $case = $this->givenHighValueInStorePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('assign_reward', $steps);
    }

    // --- In-store purchase: Tier upgrade path ---

    #[Test]
    public function purchase_triggering_tier_upgrade_processes_upgrade(): void
    {
        $case = $this->givenTierUpgradePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('evaluate_tier', $steps);
        self::assertContains('process_tier_upgrade', $steps);

        $tierIdx = array_search('evaluate_tier', $steps);
        $upgradeIdx = array_search('process_tier_upgrade', $steps);
        self::assertLessThan($upgradeIdx, $tierIdx);
    }

    // --- In-store purchase: Fraud suspected → manual review ---

    #[Test]
    public function fraud_suspected_triggers_manual_review_then_continues(): void
    {
        $case = $this->givenFraudSuspectedPurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('manual_transaction_review', $steps);

        $validateIdx = array_search('validate_transaction', $steps);
        $manualIdx = array_search('manual_transaction_review', $steps);
        $pointsIdx = array_search('calculate_points', $steps);
        self::assertLessThan($manualIdx, $validateIdx);
        self::assertLessThan($pointsIdx, $manualIdx);
    }

    // --- In-store purchase: Invalid transaction → rejected ---

    #[Test]
    public function invalid_transaction_rejects_case(): void
    {
        $case = $this->givenInvalidPurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame(
            ['validate_transaction'],
            $this->thenStepsInitializedInOrder(),
        );

        $this->thenEventLogContains('TERMINAL');
    }

    // --- Online purchase: Happy path ---

    #[Test]
    public function online_purchase_awards_points_with_bonus(): void
    {
        $case = $this->givenOnlinePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        self::assertSame([
            'validate_transaction',
            'calculate_points',
            'evaluate_tier',
        ], $this->thenStepsInitializedInOrder());
    }

    // --- Online purchase: Tier upgrade ---

    #[Test]
    public function online_purchase_with_tier_upgrade(): void
    {
        $case = $this->givenOnlineTierUpgradePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::TierUpgraded);

        $steps = $this->thenStepsInitializedInOrder();
        self::assertContains('auto_upgrade', $steps);
    }

    // --- Referral program: Happy path ---

    #[Test]
    public function valid_referral_awards_bonus_points(): void
    {
        $case = $this->givenValidReferral();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        self::assertSame([
            'validate_referral',
            'calculate_bonus',
            'notify_members',
        ], $this->thenStepsInitializedInOrder());
    }

    // --- Referral program: Duplicate referral → rejected ---

    #[Test]
    public function duplicate_referral_is_rejected(): void
    {
        $case = $this->givenDuplicateReferral();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame(
            ['validate_referral'],
            $this->thenStepsInitializedInOrder(),
        );
    }

    // --- Stage transitions ---

    #[Test]
    public function stages_are_properly_completed_during_workflow(): void
    {
        $case = $this->givenStandardInStorePurchase();

        $this->thenAllStagesAreCompleted($case);
    }

    // --- Event audit trail ---

    #[Test]
    public function event_log_records_full_audit_trail(): void
    {
        $case = $this->givenStandardInStorePurchase();

        $log = $this->eventLogger->getLog();
        self::assertNotEmpty($log);

        $this->thenEventLogStartsWithCaseStarted();
        $this->thenEventLogEndsWithCaseFinished();
        $this->thenEventLogContains('case=' . $case->id->value);
    }

    // --- Repository ---

    #[Test]
    public function repository_stores_and_retrieves_cases(): void
    {
        $case = $this->givenStandardInStorePurchase();

        $retrieved = $this->caseRepository->findById($case->id->value);
        self::assertSame($case, $retrieved);
    }

    #[Test]
    public function repository_returns_null_for_unknown_case(): void
    {
        self::assertNull($this->caseRepository->findById('nonexistent'));
    }
}
