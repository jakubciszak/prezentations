<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

/**
 * Single outcome class — the result of processing an activity.
 * Type tells you what happened, payload carries the details.
 */
final readonly class Outcome
{
    public function __construct(
        public OutcomeType $type,
        public array $payload,
    ) {}
}
