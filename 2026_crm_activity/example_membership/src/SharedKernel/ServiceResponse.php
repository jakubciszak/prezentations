<?php

declare(strict_types=1);

namespace App\SharedKernel;

use App\MembershipActivity\Domain\OutcomeType;

/**
 * What a service emits after processing an activity.
 *
 * Shared contract: services produce these, MembershipActivity consumes them.
 * In production this would be an async message/event.
 */
final readonly class ServiceResponse
{
    public function __construct(
        public string $activityId,
        public OutcomeType $outcomeType,
        public array $payload,
    ) {}
}
