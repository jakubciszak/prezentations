<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

use App\MembershipActivity\Event\MemberEvent;
use App\MembershipActivity\Event\MemberOpened;
use App\MembershipActivity\Event\PointsActivated;
use App\MembershipActivity\Event\PointsEarned;
use App\MembershipActivity\Event\PointsPending;
use App\MembershipActivity\Event\PointsSpent;

/**
 * MemberAccount — thin aggregate for a loyalty program member.
 *
 * Responsible for:
 * - recording activities (bare facts)
 * - completing activities with outcomes (from external adapters)
 * - emitting domain events
 *
 * NOT responsible for:
 * - points ledger / wallet management (→ Points context, via WalletFacade)
 * - points calculation (→ Points context, via PointsFacade)
 * - reward catalog (→ Rewards context, via RewardsFacade)
 *
 * Balance queries go through WalletFacade directly — this aggregate
 * does NOT own or expose balance state.
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
     * Record an activity — just stores the fact, no business logic.
     */
    public function record(Activity $activity): void
    {
        $this->activities[] = $activity;
    }

    /**
     * Handle an outcome produced by an external adapter.
     * Completes the activity and emits domain events.
     *
     * The ledger has already been updated by the adapter (via WalletFacade).
     * Balance info is carried in the Outcome payload.
     */
    public function handleOutcome(string $activityId, Outcome $outcome): Outcome
    {
        $activity = $this->findActivity($activityId);
        $activity->complete($outcome);
        $this->emitEventsFor($activity, $outcome);

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

    private function emitEventsFor(Activity $activity, Outcome $outcome): void
    {
        match ($outcome->type) {
            OutcomeType::PointsEarned => $this->recordEvent(new PointsEarned(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->payload['points'],
                balance: $outcome->payload['active_balance'],
                description: $activity->type->value,
            )),

            OutcomeType::PointsPending => $this->recordEvent(new PointsPending(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->payload['points'],
                awaitingReference: $outcome->payload['reference'],
                description: $activity->type->value,
            )),

            OutcomeType::PointsActivated => $this->recordEvent(new PointsActivated(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->payload['points'],
                reference: $outcome->payload['reference'],
                activeBalance: $outcome->payload['active_balance'],
            )),

            OutcomeType::PointsSpent, OutcomeType::RewardIssued => $this->recordEvent(new PointsSpent(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->payload['points'] ?? $outcome->payload['points_spent'],
                balance: $outcome->payload['active_balance'],
                description: $activity->type->value,
            )),
        };
    }

    private function recordEvent(MemberEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
