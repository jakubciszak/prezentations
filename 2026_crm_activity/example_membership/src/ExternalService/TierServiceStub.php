<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the tier management service.
 * In production this would manage customer tier status (Bronze, Silver, Gold, Platinum).
 */
final readonly class TierServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function evaluate(string $caseId, string $stepId, array $context): void
    {
        // Simulate: total_points >= 10000 triggers upgrade, otherwise unchanged
        $totalPoints = $context['total_points'] ?? 0;
        $outcome = $totalPoints >= 10_000 ? 'tier_upgrade' : 'tier_unchanged';

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'tier',
            action: 'evaluate',
            outcome: $outcome,
            metadata: [
                'current_tier' => $context['current_tier'] ?? 'bronze',
                'total_points' => $totalPoints,
            ],
        ));
    }

    public function upgrade(string $caseId, string $stepId, array $context): void
    {
        $currentTier = $context['current_tier'] ?? 'bronze';
        $newTier = match ($currentTier) {
            'bronze' => 'silver',
            'silver' => 'gold',
            'gold' => 'platinum',
            default => 'platinum',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'tier',
            action: 'upgrade',
            outcome: 'upgraded',
            metadata: [
                'previous_tier' => $currentTier,
                'new_tier' => $newTier,
                'upgraded_at' => date('c'),
            ],
        ));
    }

    public function quickEvaluate(string $caseId, string $stepId, array $context): void
    {
        $totalPoints = $context['total_points'] ?? 0;
        $outcome = $totalPoints >= 10_000 ? 'tier_upgrade' : 'tier_unchanged';

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'tier',
            action: 'quick_evaluate',
            outcome: $outcome,
            metadata: ['total_points' => $totalPoints],
        ));
    }

    public function autoUpgrade(string $caseId, string $stepId, array $context): void
    {
        $currentTier = $context['current_tier'] ?? 'bronze';
        $newTier = match ($currentTier) {
            'bronze' => 'silver',
            'silver' => 'gold',
            'gold' => 'platinum',
            default => 'platinum',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'tier',
            action: 'auto_upgrade',
            outcome: 'upgraded',
            metadata: [
                'previous_tier' => $currentTier,
                'new_tier' => $newTier,
            ],
        ));
    }
}
