<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application;

use App\MembershipActivity\Application\Flow\FlowRegistry;
use App\MembershipActivity\Domain\Activity;
use App\SharedKernel\ActivityService;
use App\SharedKernel\ServiceResponse;

/**
 * Routes activities to the appropriate service based on FlowRegistry config.
 *
 * In production, this would dispatch async messages via Messenger.
 * Here it calls services synchronously for demonstration.
 */
final class ServiceRouter
{
    /**
     * @param array<string, ActivityService> $services  service name → implementation
     */
    public function __construct(
        private readonly FlowRegistry $flows,
        private readonly array $services,
    ) {}

    public function dispatch(Activity $activity): ServiceResponse
    {
        $flow = $this->flows->resolve($activity->type);

        $service = $this->services[$flow->serviceName]
            ?? throw new \DomainException("Service not registered: '{$flow->serviceName}'");

        return $service->process($activity);
    }
}
