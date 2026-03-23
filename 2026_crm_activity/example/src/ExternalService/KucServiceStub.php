<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the KUC (Krajowy Urząd Celny) registry service.
 * In production this would call an external API.
 */
final readonly class KucServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function checkRegistry(string $caseId, string $stepId, string $nip): void
    {
        // Simulate: NIP starting with "99" is flagged, "00" not found, rest clean
        $outcome = match (true) {
            str_starts_with($nip, '99') => 'flagged',
            str_starts_with($nip, '00') => 'not_found',
            default => 'clean',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'kuc',
            action: 'check_registry',
            outcome: $outcome,
            metadata: ['nip' => $nip, 'checked_at' => date('c')],
        ));
    }

    public function manualReview(string $caseId, string $stepId): void
    {
        // Simulate: always approved in stub
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'kuc',
            action: 'manual_review',
            outcome: 'approved',
            metadata: ['reviewer' => 'compliance_bot'],
        ));
    }
}
