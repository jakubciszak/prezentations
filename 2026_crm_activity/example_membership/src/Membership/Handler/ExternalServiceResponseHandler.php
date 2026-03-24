<?php

declare(strict_types=1);

namespace App\Membership\Handler;

use App\ExternalService\ExternalServiceResponse;
use App\Membership\Engine\MembershipEngine;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles responses from external services and feeds them into the engine.
 * This is the bridge between external service stubs and the membership domain.
 */
#[AsMessageHandler]
final readonly class ExternalServiceResponseHandler
{
    public function __construct(private MembershipEngine $engine) {}

    public function __invoke(ExternalServiceResponse $response): void
    {
        $this->engine->handleServiceResponse(
            caseId: $response->caseId,
            stepId: $response->stepId,
            outcome: $response->outcome,
            metadata: $response->metadata,
        );
    }
}
