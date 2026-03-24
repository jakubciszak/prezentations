<?php

declare(strict_types=1);

namespace App\Points\Service;

use App\ExternalService\ExternalServiceResponse;
use App\Points\Model\InsufficientPointsException;
use App\Points\Model\PointsAccount;
use App\Points\Model\PointsAccountRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Points service backed by the Accounting archetype.
 *
 * Instead of simulating outcomes, this service actually maintains
 * PointsAccount ledgers with real earn/spend entries. Every points
 * change is recorded as an immutable LedgerEntry.
 */
final readonly class PointsService
{
    public function __construct(
        private MessageBusInterface $bus,
        private PointsAccountRepository $accountRepository,
    ) {}

    public function calculate(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $amount = $context['amount'] ?? 0;
        $transactionId = $context['transaction_id'] ?? '';

        $points = (int) floor($amount);

        if ($points <= 0) {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'points',
                action: 'calculate',
                outcome: 'zero_points',
                metadata: ['points_earned' => 0, 'amount' => $amount, 'rate' => '1:1'],
            ));
            return;
        }

        $account = $this->getOrCreateAccount($memberId, $context['total_points'] ?? 0);
        $entry = $account->earn($points, "Purchase points: {$amount} PLN", $transactionId);
        $this->accountRepository->save($account);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'calculate',
            outcome: 'points_calculated',
            metadata: [
                'points_earned' => $points,
                'amount' => $amount,
                'rate' => '1:1',
                'new_balance' => $account->balance(),
                'entry_id' => $entry->id,
            ],
        ));
    }

    public function calculateWithBonus(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $amount = $context['amount'] ?? 0;
        $transactionId = $context['transaction_id'] ?? '';

        $basePoints = (int) floor($amount);
        $bonusPoints = (int) floor($amount * 0.5);
        $totalPoints = $basePoints + $bonusPoints;

        $account = $this->getOrCreateAccount($memberId, $context['total_points'] ?? 0);
        $account->earn($basePoints, "Online purchase: {$amount} PLN", $transactionId);
        $bonusEntry = $account->earnBonus($bonusPoints, "Online bonus (0.5x): {$amount} PLN", $transactionId);
        $this->accountRepository->save($account);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'calculate_with_bonus',
            outcome: 'bonus_applied',
            metadata: [
                'points_earned' => $totalPoints,
                'base_points' => $basePoints,
                'bonus_points' => $bonusPoints,
                'amount' => $amount,
                'rate' => '1:1.5',
                'bonus_type' => 'online_multiplier',
                'new_balance' => $account->balance(),
                'bonus_entry_id' => $bonusEntry->id,
            ],
        ));
    }

    public function calculateReferralBonus(string $caseId, string $stepId, array $context): void
    {
        $referrerId = $context['referrer_member_id'] ?? 'unknown';
        $referredId = $context['referred_member_id'] ?? 'unknown';
        $referralCode = $context['referral_code'] ?? '';

        $referrerAccount = $this->getOrCreateAccount($referrerId);
        $referrerEntry = $referrerAccount->earnBonus(500, "Referral bonus for {$referredId}", $referralCode);
        $this->accountRepository->save($referrerAccount);

        $referredAccount = $this->getOrCreateAccount($referredId);
        $referredEntry = $referredAccount->earnBonus(200, "Welcome bonus from referral by {$referrerId}", $referralCode);
        $this->accountRepository->save($referredAccount);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'calculate_referral_bonus',
            outcome: 'bonus_calculated',
            metadata: [
                'referrer_points' => 500,
                'referred_points' => 200,
                'bonus_type' => 'referral',
                'referrer_balance' => $referrerAccount->balance(),
                'referred_balance' => $referredAccount->balance(),
                'referrer_entry_id' => $referrerEntry->id,
                'referred_entry_id' => $referredEntry->id,
            ],
        ));
    }

    public function checkBalance(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $requiredPoints = $context['reward_points_cost'] ?? 0;

        $account = $this->accountRepository->findByMemberId($memberId);
        $balance = $account?->balance() ?? 0;

        $outcome = $balance >= $requiredPoints ? 'sufficient' : 'insufficient';

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'check_balance',
            outcome: $outcome,
            metadata: [
                'balance' => $balance,
                'required' => $requiredPoints,
            ],
        ));
    }

    public function debit(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $amount = $context['reward_points_cost'] ?? 0;
        $rewardId = $context['reward_id'] ?? '';

        try {
            $account = $this->accountRepository->getByMemberId($memberId);
            $entry = $account->spend($amount, "Reward redemption: {$rewardId}", "reward:{$rewardId}");
            $this->accountRepository->save($account);

            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'points',
                action: 'debit',
                outcome: 'debited',
                metadata: [
                    'points_debited' => $amount,
                    'new_balance' => $account->balance(),
                    'entry_id' => $entry->id,
                ],
            ));
        } catch (InsufficientPointsException $e) {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'points',
                action: 'debit',
                outcome: 'insufficient',
                metadata: [
                    'requested' => $e->requested,
                    'available' => $e->available,
                ],
            ));
        }
    }

    public function refund(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $amount = $context['reward_points_cost'] ?? 0;
        $rewardId = $context['reward_id'] ?? '';

        $account = $this->accountRepository->getByMemberId($memberId);
        $entry = $account->refund($amount, "Refund for failed reward: {$rewardId}", "refund:{$rewardId}");
        $this->accountRepository->save($account);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'refund',
            outcome: 'refunded',
            metadata: [
                'points_refunded' => $amount,
                'new_balance' => $account->balance(),
                'entry_id' => $entry->id,
            ],
        ));
    }

    private function getOrCreateAccount(string $memberId, int $initialBalance = 0): PointsAccount
    {
        $account = $this->accountRepository->findByMemberId($memberId);

        if ($account === null) {
            $account = new PointsAccount($memberId, $initialBalance);
            $this->accountRepository->save($account);
        }

        return $account;
    }
}
