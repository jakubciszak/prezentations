<?php

declare(strict_types=1);

namespace App\Rewards\Service;

use App\ExternalService\ExternalServiceResponse;
use App\Rewards\Model\Redemption;
use App\Rewards\Model\RedemptionRepository;
use App\Rewards\Model\RewardCatalog;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reward service backed by a real catalog and redemption model.
 *
 * Manages the reward lifecycle: catalog lookup, availability checks,
 * issuing rewards via Redemption records, and post-purchase auto-assignment.
 */
final readonly class RewardService
{
    public function __construct(
        private MessageBusInterface $bus,
        private RewardCatalog $catalog,
        private RedemptionRepository $redemptionRepository,
    ) {}

    public function assign(string $caseId, string $stepId, array $context): void
    {
        $amount = $context['amount'] ?? 0;
        $memberId = $context['member_id'] ?? 'unknown';

        // Post-purchase auto-assign: check if purchase qualifies for a reward
        if ($amount >= 200) {
            $rewards = $this->catalog->findAvailableForPoints(0);
            $coupon = null;
            foreach ($rewards as $reward) {
                if ($reward->type === \App\Rewards\Model\RewardType::Coupon && $reward->pointsCost === 0) {
                    $coupon = $reward;
                    break;
                }
            }

            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'reward',
                action: 'assign',
                outcome: 'reward_assigned',
                metadata: [
                    'reward_type' => 'voucher',
                    'reward_value' => '10% off next purchase',
                    'reward_id' => $coupon?->id ?? 'auto-voucher',
                    'valid_until' => date('c', strtotime('+30 days')),
                ],
            ));
        } else {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'reward',
                action: 'assign',
                outcome: 'no_reward_available',
                metadata: ['reason' => 'minimum_amount_not_met'],
            ));
        }
    }

    public function validateAvailability(string $caseId, string $stepId, array $context): void
    {
        $rewardId = $context['reward_id'] ?? '';

        $reward = $this->catalog->findById($rewardId);

        if ($reward === null || !$reward->active) {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'reward',
                action: 'validate_availability',
                outcome: 'unavailable',
                metadata: [
                    'reward_id' => $rewardId,
                    'reason' => $reward === null ? 'not_found' : 'inactive',
                ],
            ));
            return;
        }

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'reward',
            action: 'validate_availability',
            outcome: 'available',
            metadata: [
                'reward_id' => $reward->id,
                'reward_name' => $reward->name,
                'points_cost' => $reward->pointsCost,
                'reward_type' => $reward->type->value,
            ],
        ));
    }

    public function issue(string $caseId, string $stepId, array $context): void
    {
        $memberId = $context['member_id'] ?? 'unknown';
        $rewardId = $context['reward_id'] ?? '';

        $reward = $this->catalog->findById($rewardId);

        if ($reward === null) {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'reward',
                action: 'issue',
                outcome: 'issue_failed',
                metadata: ['reason' => 'reward_not_found', 'reward_id' => $rewardId],
            ));
            return;
        }

        $redemption = new Redemption(
            memberId: $memberId,
            reward: $reward,
            pointsSpent: $reward->pointsCost,
        );
        $redemption->confirm();
        $this->redemptionRepository->save($redemption);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'reward',
            action: 'issue',
            outcome: 'issued',
            metadata: [
                'redemption_id' => $redemption->id,
                'reward_id' => $reward->id,
                'reward_name' => $reward->name,
                'reward_type' => $reward->type->value,
                'points_spent' => $reward->pointsCost,
            ],
        ));
    }
}
