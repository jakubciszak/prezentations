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
use App\Membership\Event\RewardRedeemed;
use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityOutcome;
use App\Membership\Model\Activity\ActivityType;
use App\Membership\Model\Points\PointsLedger;
use App\Membership\Model\Reward\Redemption;
use App\Membership\Model\Reward\Reward;

/**
 * MemberAccount — the long-lived aggregate root (Case) for a loyalty program member.
 *
 * Unlike a workflow-driven Case that follows a predefined template, MemberAccount
 * is reactive: it records Activities as they happen (purchases, deliveries, challenges)
 * and computes their effects on the points ledger.
 *
 * Every business-relevant fact is recorded as an Activity with an outcome.
 * The points ledger (Accounting archetype) tracks all balance changes.
 */
final class MemberAccount
{
    private PointsLedger $pointsLedger;

    /** @var Activity[] */
    private array $activities = [];

    /** @var Redemption[] */
    private array $redemptions = [];

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

        $account->recordEvent(new MemberOpened(
            memberId: $id->value,
            name: $name,
        ));

        return $account;
    }

    // ==================== Recording Activities ====================

    /**
     * In-store purchase — points earned immediately (1 PLN = 1 pt).
     */
    public function recordPurchase(float $amount, string $currency, string $storeId, string $transactionId): Activity
    {
        $points = (int) floor($amount);
        $entry = $this->pointsLedger->earn($points, "Purchase: {$amount} {$currency}", $transactionId);

        $activity = new Activity(
            type: ActivityType::PurchaseInStore,
            participants: ['customer' => $this->id->value, 'store' => $storeId],
            subject: ['transaction_id' => $transactionId, 'amount' => $amount, 'currency' => $currency],
            outcome: ActivityOutcome::pointsEarned($points, $entry->id),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsEarned(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $points,
            balance: $this->pointsLedger->activeBalance(),
            description: "In-store purchase: {$amount} {$currency} at {$storeId}",
        ));

        return $activity;
    }

    /**
     * Online purchase — points are pending until package delivery (1 PLN = 1.5 pts).
     */
    public function recordOnlinePurchase(float $amount, string $currency, string $orderId): Activity
    {
        $points = (int) floor($amount * 1.5);
        $entry = $this->pointsLedger->earnPending($points, "Online purchase: {$amount} {$currency}", $orderId);

        $activity = new Activity(
            type: ActivityType::OnlinePurchase,
            participants: ['customer' => $this->id->value],
            subject: ['order_id' => $orderId, 'amount' => $amount, 'currency' => $currency],
            outcome: ActivityOutcome::pointsPending($points, $entry->id, $orderId),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsPending(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $points,
            awaitingReference: $orderId,
            description: "Online purchase: {$amount} {$currency} (awaiting delivery)",
        ));

        return $activity;
    }

    /**
     * Package delivered — activates pending points for the given order.
     */
    public function recordDelivery(string $orderId): Activity
    {
        $pendingBefore = $this->pointsLedger->pendingEntriesForReference($orderId);

        if (empty($pendingBefore)) {
            throw new \DomainException("No pending points found for order '{$orderId}'");
        }

        $pointsToActivate = array_sum(array_map(fn($e) => $e->amount, $pendingBefore));
        $count = $this->pointsLedger->activateByReference($orderId);

        $activity = new Activity(
            type: ActivityType::PackageDelivered,
            participants: ['customer' => $this->id->value],
            subject: ['order_id' => $orderId],
            outcome: ActivityOutcome::pointsActivated($pointsToActivate, $count),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsActivated(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $pointsToActivate,
            reference: $orderId,
            activeBalance: $this->pointsLedger->activeBalance(),
        ));

        return $activity;
    }

    /**
     * Challenge completed — fixed bonus points earned immediately.
     */
    public function recordChallengeCompleted(string $challengeId, int $bonusPoints): Activity
    {
        $entry = $this->pointsLedger->earnBonus($bonusPoints, "Challenge: {$challengeId}", $challengeId);

        $activity = new Activity(
            type: ActivityType::ChallengeCompleted,
            participants: ['customer' => $this->id->value],
            subject: ['challenge_id' => $challengeId],
            outcome: ActivityOutcome::pointsEarned($bonusPoints, $entry->id),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsEarned(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $bonusPoints,
            balance: $this->pointsLedger->activeBalance(),
            description: "Challenge completed: {$challengeId}",
        ));

        return $activity;
    }

    /**
     * Birthday bonus — automatic bonus points.
     */
    public function recordBirthdayBonus(int $bonusPoints): Activity
    {
        $entry = $this->pointsLedger->earnBonus($bonusPoints, 'Birthday bonus', 'birthday');

        $activity = new Activity(
            type: ActivityType::BirthdayBonus,
            participants: ['customer' => $this->id->value],
            subject: [],
            outcome: ActivityOutcome::pointsEarned($bonusPoints, $entry->id),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsEarned(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $bonusPoints,
            balance: $this->pointsLedger->activeBalance(),
            description: 'Birthday bonus',
        ));

        return $activity;
    }

    /**
     * Redeem points for a reward from the catalog.
     */
    public function redeemReward(Reward $reward): Activity
    {
        if (!$reward->active) {
            throw new \DomainException("Reward '{$reward->id}' is not active");
        }

        $entry = $this->pointsLedger->spend($reward->pointsCost, "Reward: {$reward->name}", "reward:{$reward->id}");

        $redemption = new Redemption($reward, $reward->pointsCost);
        $this->redemptions[] = $redemption;

        $activity = new Activity(
            type: ActivityType::RewardRedemption,
            participants: ['customer' => $this->id->value],
            subject: ['reward_id' => $reward->id, 'reward_name' => $reward->name],
            outcome: ActivityOutcome::rewardIssued($redemption->id, $reward->id, $reward->pointsCost),
        );
        $this->activities[] = $activity;

        $this->recordEvent(new PointsSpent(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            points: $reward->pointsCost,
            balance: $this->pointsLedger->activeBalance(),
            description: "Reward redemption: {$reward->name}",
        ));

        $this->recordEvent(new RewardRedeemed(
            memberId: $this->id->value,
            activityId: $activity->id->value,
            redemptionId: $redemption->id,
            rewardId: $reward->id,
            rewardName: $reward->name,
            pointsSpent: $reward->pointsCost,
        ));

        return $activity;
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

    /** @return Redemption[] */
    public function redemptions(): array
    {
        return $this->redemptions;
    }

    /** @return MemberEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    private function recordEvent(MemberEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
