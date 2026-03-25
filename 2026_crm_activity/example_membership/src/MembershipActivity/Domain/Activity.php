<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

use DateTimeImmutable;

/**
 * Activity — a bare business fact. No business logic, no point calculations.
 *
 * Created with type + subject data, then recorded on MemberAccount.
 * A service processes it asynchronously and the account receives
 * the result via ServiceResponse, which completes the activity with an Outcome.
 */
final class Activity
{
    public readonly ActivityId $id;
    public readonly DateTimeImmutable $occurredAt;
    private ActivityStatus $status = ActivityStatus::Recorded;
    private ?Outcome $outcome = null;

    /**
     * @param array<string, mixed> $subject
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

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->subject;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->subject);
    }

    public function complete(Outcome $outcome): void
    {
        $this->outcome = $outcome;
        $this->status = ActivityStatus::Completed;
    }

    public function fail(): void
    {
        $this->status = ActivityStatus::Failed;
    }

    public function status(): ActivityStatus
    {
        return $this->status;
    }

    public function outcome(): ?Outcome
    {
        return $this->outcome;
    }

    public function isCompleted(): bool
    {
        return $this->status === ActivityStatus::Completed;
    }
}
