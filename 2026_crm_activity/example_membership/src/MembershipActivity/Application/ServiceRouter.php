<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application;

use App\MembershipActivity\Application\Adapter\ActivityAdapter;
use App\MembershipActivity\Application\Flow\FlowRegistry;
use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;

/**
 * Routes activities to the appropriate adapter based on FlowRegistry config.
 *
 * In production, dispatch could be async via Messenger —
 * the adapter would emit a command and the result would come back as an event.
 */
final class ServiceRouter
{
    /**
     * @param array<string, ActivityAdapter> $adapters  service name → adapter
     */
    public function __construct(
        private readonly FlowRegistry $flows,
        private readonly array $adapters,
    ) {}

    public function dispatch(Activity $activity): Outcome
    {
        $flow = $this->flows->resolve($activity->type);

        $adapter = $this->adapters[$flow->serviceName]
            ?? throw new \DomainException("Adapter not registered: '{$flow->serviceName}'");

        return $adapter->handle($activity);
    }
}
