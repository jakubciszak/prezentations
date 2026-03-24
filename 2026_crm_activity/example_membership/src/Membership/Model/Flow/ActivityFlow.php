<?php

declare(strict_types=1);

namespace App\Membership\Model\Flow;

use App\Membership\Model\Activity\ActivityType;

/**
 * Declares: when an Activity of this type arrives → route to this service.
 */
final readonly class ActivityFlow
{
    public function __construct(
        public ActivityType $activityType,
        public string $serviceName,
    ) {}

    public static function route(ActivityType $type, string $service): self
    {
        return new self($type, $service);
    }
}
