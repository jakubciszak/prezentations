<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

use App\MembershipActivity\Event\MemberEvent;
use App\MembershipActivity\Event\MemberOpened;
use App\MembershipActivity\Event\PointsActivated;
use App\MembershipActivity\Event\PointsEarned;
use App\MembershipActivity\Event\PointsPending;
use App\MembershipActivity\Event\PointsSpent;
use App\Points\Domain\PointsLedger;

/**
 * MemberAccount — long-lived aggregate for a loyalty program member.
 *
 * Two entry points:
 * - record(Activity)                  — stores a new activity (bare fact)
 * - handleOutcome(activityId, outcome) — applies outcome to ledger, completes activity
 *
 * The Outcome comes from an external adapter (via ServiceRouter),
 * which called the appropriate bounded context facade.
 * The account only interprets OutcomeType → ledger operation.
 */
final class MemberAccount
{
    private PointsLedger $pointsLedger;

    /** @var Activity[] */
    private array $activities = [];

    /** @var MemberEvent[] */
    private array $recordedEvents = [];

    private function __construct(
        public readonly MemberId $id,
        public readonly string $name,
    ) {
        $this->pointsLedger = new PointsLedger();
    }

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
     * Handle an outcome produced by an external service (via adapter).
     * Applies the effect to the ledger, completes the activity, emits events.
     */
    public function handleOutcome(string $activityId, Outcome $outcome): Outcome
    {
        $activity = $this->findActivity($activityId);

        $finalOutcome = $this->applyToLedger($outcome);
        $activity->complete($finalOutcome);

        $this->emitEventsFor($activity, $finalOutcome);

        return $finalOutcome;
    }

    // ==================== Queries ====================

    public function activeBalance(): int
    {
        return $this->pointsLedger->activeBalance();
    }

    public function pendingBalance(): int
    {
        return $this->pointsLedger->pendingBalance();
    }

    public function totalBalance(): int
    {
        return $this->pointsLedger->totalBalance();
    }

    public function ledger(): PointsLedger
    {
        return $this->pointsLedger;
    }

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

    /**
     * Apply outcome to ledger. For activation, enrich with actual points
     * (the external service doesn't know the amount — the ledger does).
     */
    private function applyToLedger(Outcome $outcome): Outcome
    {
        if ($outcome->type === OutcomeType::PointsActivated) {
            return $this->applyActivation($outcome);
        }

        match ($outcome->type) {
            OutcomeType::PointsEarned => $this->pointsLedger->earn(
                $outcome->payload['points'],
                $outcome->type->value,
                $outcome->payload['reference'] ?? '',
            ),

            OutcomeType::PointsPending => $this->pointsLedger->earnPending(
                $outcome->payload['points'],
                $outcome->type->value,
                $outcome->payload['reference'],
            ),

            OutcomeType::PointsSpent => $this->pointsLedger->spend(
                $outcome->payload['points'],
                $outcome->type->value,
                $outcome->payload['reference'] ?? '',
            ),

            OutcomeType::RewardIssued => $this->pointsLedger->spend(
                $outcome->payload['points_spent'],
                $outcome->type->value,
                "reward:{$outcome->payload['reward_id']}",
            ),

            default => null,
        };

        return $outcome;
    }

    private function applyActivation(Outcome $outcome): Outcome
    {
        $reference = $outcome->payload['reference'];

        $pending = $this->pointsLedger->pendingEntriesForReference($reference);
        $points = array_sum(array_map(fn($e) => $e->amount, $pending));

        $this->pointsLedger->activateByReference($reference);

        return new Outcome($outcome->type, [
            ...$outcome->payload,
            'points' => $points,
        ]);
    }

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
                balance: $this->pointsLedger->activeBalance(),
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
                activeBalance: $this->pointsLedger->activeBalance(),
            )),
            OutcomeType::PointsSpent, OutcomeType::RewardIssued => $this->recordEvent(new PointsSpent(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->payload['points'] ?? $outcome->payload['points_spent'],
                balance: $this->pointsLedger->activeBalance(),
                description: $activity->type->value,
            )),
        };
    }

    private function recordEvent(MemberEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
