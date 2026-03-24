<?php

declare(strict_types=1);

namespace App\Membership\Model\Activity;

use DateTimeImmutable;

/**
 * Activity — a business-relevant fact about what happened with a member.
 *
 * Not a technical log entry. Each Activity records WHO did WHAT, with WHOM,
 * concerning WHAT subject, and WHAT was the outcome. This follows the Activity
 * archetype from CRM modeling.
 */
final readonly class Activity
{
    public ActivityId $id;
    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, string> $participants  role → identifier
     * @param array<string, mixed>  $subject       what the activity concerns
     */
    public function __construct(
        public ActivityType $type,
        public array $participants,
        public array $subject,
        public ActivityOutcome $outcome,
    ) {
        $this->id = ActivityId::generate();
        $this->occurredAt = new DateTimeImmutable();
    }
}
