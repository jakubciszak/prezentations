<?php

declare(strict_types=1);

namespace App\ExternalService;

use App\Points\Service\PointsService;
use App\Rewards\Service\RewardService;

/**
 * Routes step execution to the appropriate service.
 *
 * Points and Rewards are backed by real domain models (Accounting archetype
 * for Points, catalog + redemptions for Rewards). Transaction, Tier, and
 * Notification remain as stubs.
 */
final readonly class ServiceDispatcher
{
    public function __construct(
        private TransactionServiceStub $transaction,
        private PointsService $points,
        private TierServiceStub $tier,
        private RewardService $reward,
        private NotificationServiceStub $notification,
    ) {}

    public function dispatch(
        string $caseId,
        string $stepId,
        string $service,
        string $action,
        array $context = [],
    ): void {
        match ($service) {
            'transaction' => match ($action) {
                'validate' => $this->transaction->validate($caseId, $stepId, $context),
                'validate_online' => $this->transaction->validateOnline($caseId, $stepId, $context),
                'manual_review' => $this->transaction->manualReview($caseId, $stepId),
                'validate_referral' => $this->transaction->validateReferral($caseId, $stepId, $context),
            },
            'points' => match ($action) {
                'calculate' => $this->points->calculate($caseId, $stepId, $context),
                'calculate_with_bonus' => $this->points->calculateWithBonus($caseId, $stepId, $context),
                'calculate_referral_bonus' => $this->points->calculateReferralBonus($caseId, $stepId, $context),
                'check_balance' => $this->points->checkBalance($caseId, $stepId, $context),
                'debit' => $this->points->debit($caseId, $stepId, $context),
                'refund' => $this->points->refund($caseId, $stepId, $context),
            },
            'tier' => match ($action) {
                'evaluate' => $this->tier->evaluate($caseId, $stepId, $context),
                'upgrade' => $this->tier->upgrade($caseId, $stepId, $context),
                'quick_evaluate' => $this->tier->quickEvaluate($caseId, $stepId, $context),
                'auto_upgrade' => $this->tier->autoUpgrade($caseId, $stepId, $context),
            },
            'reward' => match ($action) {
                'assign' => $this->reward->assign($caseId, $stepId, $context),
                'validate_availability' => $this->reward->validateAvailability($caseId, $stepId, $context),
                'issue' => $this->reward->issue($caseId, $stepId, $context),
            },
            'notification' => match ($action) {
                'send_referral_notification' => $this->notification->sendReferralNotification($caseId, $stepId, $context),
            },
            default => throw new \InvalidArgumentException("Unknown service: {$service}"),
        };
    }
}
