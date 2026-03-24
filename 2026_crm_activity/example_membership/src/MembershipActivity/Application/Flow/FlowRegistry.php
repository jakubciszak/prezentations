<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Flow;

use App\MembershipActivity\Domain\ActivityType;

/**
 * Registry of activity flows — the declarative configuration
 * that tells the system how to route each activity type.
 */
final readonly class FlowRegistry
{
    /** @var array<string, ActivityFlow> */
    private array $flows;

    public function __construct(ActivityFlow ...$flows)
    {
        $indexed = [];
        foreach ($flows as $flow) {
            $indexed[$flow->activityType->value] = $flow;
        }
        $this->flows = $indexed;
    }

    public function resolve(ActivityType $type): ActivityFlow
    {
        return $this->flows[$type->value]
            ?? throw new \DomainException("No flow defined for activity type '{$type->value}'");
    }

    public function has(ActivityType $type): bool
    {
        return isset($this->flows[$type->value]);
    }
}
