<?php

declare(strict_types=1);

namespace App\Membership\Model;

use App\Membership\Event\ActivityRecorded;
use App\Membership\Event\MemberEvent;
use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityOutcome;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\Points\PointsLedger;
use App\Membership\Model\Reaction\ReactionRules;

/**
 * MemberAccount — long-lived aggregate root for a loyalty program member.
 *
 * Fully generic: one record() method accepts any Activity.
 * ReactionRules (injected) decide what happens — the aggregate
 * doesn't need dedicated methods per activity type.
 *
 * Rules are the "what if X happens" declarations.
 * The aggregate is the "where it happens" — it owns the ledger and activity log.
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
        private readonly ReactionRules $rules,
    ) {
        $this->pointsLedger = new PointsLedger();
    }

    public static function open(MemberId $id, string $name, ReactionRules $rules): self
    {
        $account = new self($id, $name, $rules);

        $account->recordEvent(new MemberOpened(
            memberId: $id->value,
            name: $name,
        ));

        return $account;
    }

    /**
     * Record any activity — the matching ReactionRule decides the effect.
     */
    public function record(Activity $activity): ActivityOutcome
    {
        $rule = $this->rules->findFor($activity);
        $outcome = $rule->apply($activity, $this->pointsLedger);

        $activity->withOutcome($outcome);
        $this->activities[] = $activity;

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

    private function emitEventsFor(Activity $activity, ActivityOutcome $outcome): void
    {
        match ($outcome->type) {
            OutcomeType::PointsEarned => $this->recordEvent(new PointsEarned(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->details['points'],
                balance: $this->pointsLedger->activeBalance(),
                description: $activity->type->value,
            )),
            OutcomeType::PointsPending => $this->recordEvent(new PointsPending(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->details['points'],
                awaitingReference: $outcome->details['awaiting'],
                description: $activity->type->value,
            )),
            OutcomeType::PointsActivated => $this->recordEvent(new PointsActivated(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->details['points'],
                reference: $activity->subject['order_id'] ?? '',
                activeBalance: $this->pointsLedger->activeBalance(),
            )),
            OutcomeType::PointsSpent => $this->recordEvent(new PointsSpent(
                memberId: $this->id->value,
                activityId: $activity->id->value,
                points: $outcome->details['points'],
                balance: $this->pointsLedger->activeBalance(),
                description: $activity->type->value,
            )),
            default => null,
        };
    }

    private function recordEvent(MemberEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
