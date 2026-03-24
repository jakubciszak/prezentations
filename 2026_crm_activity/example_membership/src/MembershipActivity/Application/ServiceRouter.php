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
 * Passes memberId so the adapter can record wallet operations
 * in the Points context on behalf of the member.
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

    public function dispatch(Activity $activity, string $memberId): Outcome
    {
        $flow = $this->flows->resolve($activity->type);

        $adapter = $this->adapters[$flow->serviceName]
            ?? throw new \DomainException("Adapter not registered: '{$flow->serviceName}'");

        return $adapter->handle($activity, $memberId);
    }
}
