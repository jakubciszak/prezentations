<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the prospect registration form service.
 */
final readonly class ProspectFormServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function collect(string $caseId, string $stepId, array $formData): void
    {
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'prospect_form',
            action: 'collect',
            outcome: 'completed',
            metadata: $formData,
        ));
    }
}
