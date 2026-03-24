<?php

declare(strict_types=1);

namespace App\Membership\Model;

use App\Membership\Event\MemberEvent;
use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\Outcome;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\Points\PointsLedger;
use App\Membership\Service\ServiceResponse;

/**
 * MemberAccount — long-lived aggregate for a loyalty program member.
 *
 * Two entry points:
 * - record(Activity)                 — stores a new activity (bare fact)
 * - handleServiceResponse(response)  — receives what a service decided,
 *                                      applies to ledger, completes activity
 *
 * The account never decides business logic (how many points, what reward).
 * It only interprets OutcomeType → ledger operation.
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
     * Handle a service response — applies the outcome to the ledger,
     * completes the activity, and emits domain events.
     */
    public function handleServiceResponse(ServiceResponse $response): Outcome
    {
        $activity = $this->findActivity($response->activityId);

        $outcome = $this->applyToLedger($response->outcomeType, $response->payload);
        $activity->complete($outcome);

        $this->emitEventsFor($activity, $outcome);

        return $outcome;
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

    private function applyToLedger(OutcomeType $type, array $payload): Outcome
    {
        match ($type) {
            OutcomeType::PointsEarned => $this->pointsLedger->earn(
                $payload['points'],
                $type->value,
                $payload['reference'] ?? '',
            ),

            OutcomeType::PointsPending => $this->pointsLedger->earnPending(
                $payload['points'],
                $type->value,
                $payload['reference'],
            ),

            OutcomeType::PointsActivated => $this->pointsLedger->activateByReference(
                $payload['reference'],
            ),

            OutcomeType::PointsSpent => $this->pointsLedger->spend(
                $payload['points'],
                $type->value,
                $payload['reference'] ?? '',
            ),

            OutcomeType::RewardIssued => $this->pointsLedger->spend(
                $payload['points_spent'],
                $type->value,
                "reward:{$payload['reward_id']}",
            ),
        };

        return new Outcome($type, $payload);
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
                points: $outcome->payload['points'] ?? 0,
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
