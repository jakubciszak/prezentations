<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the points calculation service.
 * In production this would call the loyalty points engine.
 */
final readonly class PointsServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function calculate(string $caseId, string $stepId, array $context): void
    {
        // Simulate: 1 point per 1 PLN, minimum 10 PLN for points
        $amount = $context['amount'] ?? 0;
        $points = (int) floor($amount);

        $outcome = $points > 0 ? 'points_calculated' : 'zero_points';

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'calculate',
            outcome: $outcome,
            metadata: [
                'points_earned' => $points,
                'amount' => $amount,
                'rate' => '1:1',
            ],
        ));
    }

    public function calculateWithBonus(string $caseId, string $stepId, array $context): void
    {
        // Simulate: online purchases get 1.5x points
        $amount = $context['amount'] ?? 0;
        $points = (int) floor($amount * 1.5);

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'points',
            action: 'calculate_with_bonus',
            outcome: 'bonus_applied',
            metadata: [
                'points_earned' => $points,
                'amount' => $amount,
                'rate' => '1:1.5',
                'bonus_type' => 'online_multiplier',
            ],
        ));
    }

    public function calculateReferralBonus(string $caseId, string $stepId, array $context): void
    {
        // Simulate: fixed 500 points for referral
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
            ],
        ));
    }
}
