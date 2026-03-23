<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the document collection service.
 */
final readonly class DocumentServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function requestBasic(string $caseId, string $stepId): void
    {
        // Simulate: all documents received
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'documents',
            action: 'request_basic',
            outcome: 'all_received',
            metadata: [
                'documents' => ['registration_certificate', 'id_scan'],
                'received_at' => date('c'),
            ],
        ));
    }

    public function requestExtended(string $caseId, string $stepId): void
    {
        // Simulate: all documents received
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'documents',
            action: 'request_extended',
            outcome: 'all_received',
            metadata: [
                'documents' => [
                    'registration_certificate',
                    'id_scan',
                    'financial_statements',
                    'beneficial_owners_declaration',
                ],
            ],
        ));
    }
}
