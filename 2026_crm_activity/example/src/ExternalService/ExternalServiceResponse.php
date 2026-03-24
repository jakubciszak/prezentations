<?php

declare(strict_types=1);

namespace App\ExternalService;

/**
 * Message dispatched by external service stubs via Symfony Messenger.
 * Represents a response from an external system (KUC, documents, etc.).
 */
final readonly class ExternalServiceResponse
{
    public function __construct(
        public string $caseId,
        public string $stepId,
        public string $service,
        public string $action,
        public string $outcome,
        public array $metadata = [],
    ) {}
}
