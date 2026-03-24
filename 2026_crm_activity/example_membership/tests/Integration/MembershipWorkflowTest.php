<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Membership\Model\CaseOutcome;
use App\Membership\Model\MembershipCase;
use App\Points\Model\EntryType;
use App\Points\Model\PointsAccount;
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

    // --- Points Accounting: Ledger entries are created ---

    #[Test]
    public function purchase_creates_earn_entry_in_points_account(): void
    {
        $case = $this->givenStandardInStorePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $account = $this->pointsAccountRepository->findByMemberId('MBR-001');
        self::assertNotNull($account);

        $earnEntries = $account->entriesOfType(EntryType::Earn);
        self::assertNotEmpty($earnEntries);
        self::assertSame(150, $earnEntries[0]->amount);
    }

    #[Test]
    public function online_purchase_creates_base_and_bonus_entries(): void
    {
        $case = $this->givenOnlinePurchase();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $account = $this->pointsAccountRepository->findByMemberId('MBR-010');
        self::assertNotNull($account);

        $earnEntries = $account->entriesOfType(EntryType::Earn);
        $bonusEntries = $account->entriesOfType(EntryType::BonusEarn);
        self::assertCount(1, $earnEntries);
        self::assertCount(1, $bonusEntries);
        self::assertSame(200, $earnEntries[0]->amount);
        self::assertSame(100, $bonusEntries[0]->amount);
    }

    #[Test]
    public function referral_creates_bonus_entries_for_both_members(): void
    {
        $case = $this->givenValidReferral();

        $this->thenCaseIsCompletedWith($case, CaseOutcome::PointsAwarded);

        $referrerAccount = $this->pointsAccountRepository->findByMemberId('MBR-020');
        $referredAccount = $this->pointsAccountRepository->findByMemberId('MBR-021');

        self::assertNotNull($referrerAccount);
        self::assertNotNull($referredAccount);
        self::assertSame(500, $referrerAccount->totalEarned());
        self::assertSame(200, $referredAccount->totalEarned());
    }

    // --- Reward Redemption: Happy path ---

    #[Test]
    public function member_redeems_reward_successfully(): void
    {
        // Pre-create account with sufficient balance
        $account = new PointsAccount('MBR-100', 5_000);
        $this->pointsAccountRepository->save($account);

        $case = $this->givenCaseStarted('reward_redemption', MemberDataFactory::rewardRedemption());

        $this->thenCaseIsCompletedWith($case, CaseOutcome::RewardRedeemed);

        self::assertSame([
            'validate_reward',
            'check_balance',
            'debit_points',
            'issue_reward',
        ], $this->thenStepsInitializedInOrder());

        // Verify points were debited
        $updatedAccount = $this->pointsAccountRepository->getByMemberId('MBR-100');
        self::assertSame(4_000, $updatedAccount->balance());

        $spendEntries = $updatedAccount->entriesOfType(EntryType::Spend);
        self::assertCount(1, $spendEntries);
        self::assertSame(-1_000, $spendEntries[0]->amount);

        // Verify redemption was created
        $redemptions = $this->redemptionRepository->findByMemberId('MBR-100');
        self::assertCount(1, $redemptions);
        self::assertSame('RWD-10PCT', $redemptions[0]->reward->id);
    }

    // --- Reward Redemption: Insufficient balance ---

    #[Test]
    public function redemption_rejected_when_insufficient_balance(): void
    {
        // Pre-create account with insufficient balance
        $account = new PointsAccount('MBR-101', 100);
        $this->pointsAccountRepository->save($account);

        $case = $this->givenCaseStarted('reward_redemption', MemberDataFactory::expensiveRewardRedemption());

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame([
            'validate_reward',
            'check_balance',
        ], $this->thenStepsInitializedInOrder());

        // Balance unchanged
        self::assertSame(100, $this->pointsAccountRepository->getByMemberId('MBR-101')->balance());
    }

    // --- Reward Redemption: Unavailable reward ---

    #[Test]
    public function redemption_rejected_for_nonexistent_reward(): void
    {
        $case = $this->givenCaseStarted('reward_redemption', MemberDataFactory::unavailableRewardRedemption());

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame(
            ['validate_reward'],
            $this->thenStepsInitializedInOrder(),
        );
    }

    // --- Reward Redemption: Inactive reward ---

    #[Test]
    public function redemption_rejected_for_inactive_reward(): void
    {
        $case = $this->givenCaseStarted('reward_redemption', MemberDataFactory::inactiveRewardRedemption());

        $this->thenCaseIsCompletedWith($case, CaseOutcome::Rejected);

        self::assertSame(
            ['validate_reward'],
            $this->thenStepsInitializedInOrder(),
        );
    }
}
