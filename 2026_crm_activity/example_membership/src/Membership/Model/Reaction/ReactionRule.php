<?php

declare(strict_types=1);

namespace App\Membership\Model\Reaction;

use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityOutcome;
use App\Membership\Model\Activity\ActivityType;
use App\Membership\Model\Points\PointsLedger;

/**
 * Declarative rule: when Activity of type X arrives, apply effect Y to the ledger.
 *
 * Rules are pure declarations — they don't know about specific business objects
 * (Reward, Challenge, etc.). They only know about activities and the points ledger.
 */
final readonly class ReactionRule
{
    private function __construct(
        public ActivityType $activityType,
        public PointsEffect $effect,
        private ?\Closure $amountResolver,
        private ?\Closure $referenceResolver,
    ) {}

    public static function earn(ActivityType $on, \Closure $amount): self
    {
        return new self($on, PointsEffect::Earn, $amount, null);
    }

    public static function earnPending(ActivityType $on, \Closure $amount, \Closure $reference): self
    {
        return new self($on, PointsEffect::EarnPending, $amount, $reference);
    }

    public static function earnBonus(ActivityType $on, \Closure $amount): self
    {
        return new self($on, PointsEffect::EarnBonus, $amount, null);
    }

    public static function activate(ActivityType $on, \Closure $reference): self
    {
        return new self($on, PointsEffect::Activate, null, $reference);
    }

    public static function spend(ActivityType $on, \Closure $amount): self
    {
        return new self($on, PointsEffect::Spend, $amount, null);
    }

    public function apply(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        return match ($this->effect) {
            PointsEffect::Earn => $this->applyEarn($activity, $ledger),
            PointsEffect::EarnPending => $this->applyEarnPending($activity, $ledger),
            PointsEffect::EarnBonus => $this->applyEarnBonus($activity, $ledger),
            PointsEffect::Activate => $this->applyActivate($activity, $ledger),
            PointsEffect::Spend => $this->applySpend($activity, $ledger),
        };
    }

    private function applyEarn(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        $amount = ($this->amountResolver)($activity);
        $entry = $ledger->earn($amount, $activity->type->value, $this->resolveReference($activity));

        return ActivityOutcome::pointsEarned($amount, $entry->id);
    }

    private function applyEarnPending(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        $amount = ($this->amountResolver)($activity);
        $reference = ($this->referenceResolver)($activity);
        $entry = $ledger->earnPending($amount, $activity->type->value, $reference);

        return ActivityOutcome::pointsPending($amount, $entry->id, $reference);
    }

    private function applyEarnBonus(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        $amount = ($this->amountResolver)($activity);
        $entry = $ledger->earnBonus($amount, $activity->type->value, $this->resolveReference($activity));

        return ActivityOutcome::pointsEarned($amount, $entry->id);
    }

    private function applyActivate(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        $reference = ($this->referenceResolver)($activity);
        $pending = $ledger->pendingEntriesForReference($reference);

        if (empty($pending)) {
            throw new \DomainException("No pending points found for reference '{$reference}'");
        }

        $points = array_sum(array_map(fn($e) => $e->amount, $pending));
        $count = $ledger->activateByReference($reference);

        return ActivityOutcome::pointsActivated($points, $count);
    }

    private function applySpend(Activity $activity, PointsLedger $ledger): ActivityOutcome
    {
        $amount = ($this->amountResolver)($activity);
        $entry = $ledger->spend($amount, $activity->type->value, $this->resolveReference($activity));

        return ActivityOutcome::pointsSpent($amount, $entry->id);
    }

    private function resolveReference(Activity $activity): string
    {
        if ($this->referenceResolver !== null) {
            return ($this->referenceResolver)($activity);
        }

        return $activity->id->value;
    }
}
