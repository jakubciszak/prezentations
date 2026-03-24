<?php

declare(strict_types=1);

namespace App\Membership\Model\Activity;

use DateTimeImmutable;

/**
 * Activity — a business-relevant fact recorded on a member account.
 *
 * Generic by design: type + subject (arbitrary key-value data).
 * The aggregate doesn't need to understand the internals —
 * ReactionRules extract what they need via get().
 *
 * Outcome is attached after the rule is applied.
 */
final class Activity
{
    public readonly ActivityId $id;
    public readonly DateTimeImmutable $occurredAt;
    private ?ActivityOutcome $outcome = null;

    /**
     * @param array<string, mixed> $subject  arbitrary data about the activity
     */
    public function __construct(
        public readonly ActivityType $type,
        public readonly array $subject = [],
    ) {
        $this->id = ActivityId::generate();
        $this->occurredAt = new DateTimeImmutable();
    }

    public function get(string $key): mixed
    {
        return $this->subject[$key]
            ?? throw new \InvalidArgumentException("Missing subject key: '{$key}'");
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->subject);
    }

    public function withOutcome(ActivityOutcome $outcome): void
    {
        if ($this->outcome !== null) {
            throw new \LogicException('Activity outcome already set');
        }
        $this->outcome = $outcome;
    }

    public function outcome(): ?ActivityOutcome
    {
        return $this->outcome;
    }
}
