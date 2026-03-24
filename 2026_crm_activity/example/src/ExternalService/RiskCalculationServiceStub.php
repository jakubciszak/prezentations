<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the risk calculation engine.
 */
final readonly class RiskCalculationServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function calculate(string $caseId, string $stepId, array $clientData): void
    {
        // Simulate: risk level based on revenue
        $revenue = $clientData['annual_revenue'] ?? 0;
        $outcome = match (true) {
            $revenue > 10_000_000 => 'high_risk',
            $revenue > 1_000_000 => 'medium_risk',
            default => 'low_risk',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'risk_calculation',
            action: 'calculate',
            outcome: $outcome,
            metadata: ['risk_score' => $revenue > 10_000_000 ? 85 : ($revenue > 1_000_000 ? 55 : 20)],
        ));
    }

    public function quickCheck(string $caseId, string $stepId): void
    {
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'risk_calculation',
            action: 'quick_check',
            outcome: 'passed',
        ));
    }

    public function finalCheck(string $caseId, string $stepId): void
    {
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'risk_calculation',
            action: 'final_check',
            outcome: 'approved',
            metadata: ['approved_by' => 'auto_system'],
        ));
    }

    public function autoApprove(string $caseId, string $stepId): void
    {
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'risk_calculation',
            action: 'auto_approve',
            outcome: 'approved',
        ));
    }
}
