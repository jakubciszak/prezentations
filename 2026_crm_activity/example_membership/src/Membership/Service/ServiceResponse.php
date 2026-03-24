<?php

declare(strict_types=1);

namespace App\Membership\Service;

use App\Membership\Model\Activity\OutcomeType;

/**
 * What a service emits after processing an activity.
 *
 * In production this would be an async message/event.
 * The membership aggregate interprets it and applies to the ledger.
 */
final readonly class ServiceResponse
{
    public function __construct(
        public string $activityId,
        public OutcomeType $outcomeType,
        public array $payload,
    ) {}
}
