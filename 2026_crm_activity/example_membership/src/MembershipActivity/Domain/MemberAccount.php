<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

use App\MembershipActivity\Event\ActivityCompleted;
use App\MembershipActivity\Event\ActivityInitialized;
use App\MembershipActivity\Event\MemberEvent;
use App\MembershipActivity\Event\MemberOpened;

/**
 * MemberAccount — thin aggregate for a loyalty program member.
 *
 * Emits generic lifecycle events:
 * - MemberOpened         — account created
 * - ActivityInitialized  — activity recorded (type + input payload)
 * - ActivityCompleted    — activity processed (type + outcome payload)
 *
 * NO knowledge of points, rewards, or any other bounded context.
 * Downstream contexts subscribe to ActivityCompleted and interpret
 * the outcomeType + outcomePayload as they see fit.
 */
final class MemberAccount
{
    /** @var Activity[] */
    private array $activities = [];

    /** @var MemberEvent[] */
    private array $recordedEvents = [];

    private function __construct(
        public readonly MemberId $id,
        public readonly string $name,
    ) {}

    public static function open(MemberId $id, string $name): self
    {
        $account = new self($id, $name);
        $account->recordEvent(new MemberOpened(memberId: $id->value, name: $name));

        return $account;
    }

    /**
     * Record an activity and emit ActivityInitialized.
     */
    public function record(Activity $activity): void
    {
        $this->activities[] = $activity;

        $this->recordEvent(new ActivityInitialized(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            activityType: $activity->type->value,
            payload: $activity->data(),
        ));
    }

    /**
     * Handle an outcome produced by an external adapter.
     * Completes the activity and emits ActivityCompleted.
     */
    public function handleOutcome(string $activityId, Outcome $outcome): Outcome
    {
        $activity = $this->findActivity($activityId);
        $activity->complete($outcome);

        $this->recordEvent(new ActivityCompleted(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            activityType: $activity->type->value,
            outcomeType: $outcome->type->value,
            outcomePayload: $outcome->payload,
        ));

        return $outcome;
    }

    // ==================== Queries ====================

    /** @return Activity[] */
    public function activities(): array
    {
        return $this->activities;
    }

    /** @return MemberEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    // ==================== Internals ====================

    private function findActivity(string $activityId): Activity
    {
        foreach ($this->activities as $activity) {
            if ($activity->id->value === $activityId) {
                return $activity;
            }
        }

        throw new \DomainException("Activity not found: {$activityId}");
    }

    private function recordEvent(MemberEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
