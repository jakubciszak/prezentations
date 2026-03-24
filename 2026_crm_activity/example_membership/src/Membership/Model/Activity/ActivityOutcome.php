<?php

declare(strict_types=1);

namespace App\Membership\Model\Activity;

/**
 * The result/effect of an Activity.
 *
 * Not always about points — an Activity can result in a tier change,
 * benefit unlock, reward issuance, or simply an acknowledgement
 * (e.g., profile updated, preferences changed).
 */
final readonly class ActivityOutcome
{
    private function __construct(
        public OutcomeType $type,
        public array $details,
    ) {}

    public static function pointsEarned(int $points, string $entryId): self
    {
        return new self(OutcomeType::PointsEarned, [
            'points' => $points,
            'entry_id' => $entryId,
        ]);
    }

    public static function pointsPending(int $points, string $entryId, string $awaitingReference): self
    {
        return new self(OutcomeType::PointsPending, [
            'points' => $points,
            'entry_id' => $entryId,
            'awaiting' => $awaitingReference,
        ]);
    }

    public static function pointsActivated(int $points, int $entriesActivated): self
    {
        return new self(OutcomeType::PointsActivated, [
            'points' => $points,
            'entries_activated' => $entriesActivated,
        ]);
    }

    public static function pointsSpent(int $points, string $entryId): self
    {
        return new self(OutcomeType::PointsSpent, [
            'points' => $points,
            'entry_id' => $entryId,
        ]);
    }

    public static function rewardIssued(string $redemptionId, string $rewardId, int $pointsSpent): self
    {
        return new self(OutcomeType::RewardIssued, [
            'redemption_id' => $redemptionId,
            'reward_id' => $rewardId,
            'points_spent' => $pointsSpent,
        ]);
    }

    public static function tierChanged(string $previousTier, string $newTier): self
    {
        return new self(OutcomeType::TierChanged, [
            'previous_tier' => $previousTier,
            'new_tier' => $newTier,
        ]);
    }

    public static function benefitUnlocked(string $benefitId, string $benefitName): self
    {
        return new self(OutcomeType::BenefitUnlocked, [
            'benefit_id' => $benefitId,
            'benefit_name' => $benefitName,
        ]);
    }

    public static function acknowledged(string $description): self
    {
        return new self(OutcomeType::Acknowledged, [
            'description' => $description,
        ]);
    }
}
