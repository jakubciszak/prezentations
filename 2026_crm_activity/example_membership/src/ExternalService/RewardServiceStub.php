<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the reward fulfillment service.
 * In production this would manage vouchers, discounts, and rewards.
 */
final readonly class RewardServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function assign(string $caseId, string $stepId, array $context): void
    {
        // Simulate: purchases over 200 PLN get a reward voucher
        $amount = $context['amount'] ?? 0;

        if ($amount >= 200) {
            $this->bus->dispatch(new ExternalServiceResponse(
                caseId: $caseId,
                stepId: $stepId,
                service: 'reward',
                action: 'assign',
                outcome: 'reward_assigned',
                metadata: [
                    'reward_type' => 'voucher',
                    'reward_value' => '10% off next purchase',
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
}
