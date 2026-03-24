<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Membership\Model\Outcome;

final class OutcomeFactory
{
    public static function valid(array $metadata = []): Outcome
    {
        return new Outcome('valid', $metadata);
    }

    public static function invalid(): Outcome
    {
        return new Outcome('invalid');
    }

    public static function fraudSuspected(): Outcome
    {
        return new Outcome('fraud_suspected');
    }

    public static function approved(array $metadata = []): Outcome
    {
        return new Outcome('approved', $metadata);
    }

    public static function rejected(): Outcome
    {
        return new Outcome('rejected');
    }

    public static function pointsCalculated(array $metadata = []): Outcome
    {
        return new Outcome('points_calculated', $metadata);
    }

    public static function zeroPoints(): Outcome
    {
        return new Outcome('zero_points');
    }

    public static function bonusApplied(array $metadata = []): Outcome
    {
        return new Outcome('bonus_applied', $metadata);
    }

    public static function tierUnchanged(): Outcome
    {
        return new Outcome('tier_unchanged');
    }

    public static function tierUpgrade(): Outcome
    {
        return new Outcome('tier_upgrade');
    }

    public static function upgraded(array $metadata = []): Outcome
    {
        return new Outcome('upgraded', $metadata);
    }

    public static function rewardAssigned(array $metadata = []): Outcome
    {
        return new Outcome('reward_assigned', $metadata);
    }

    public static function noRewardAvailable(): Outcome
    {
        return new Outcome('no_reward_available');
    }

    public static function bonusCalculated(array $metadata = []): Outcome
    {
        return new Outcome('bonus_calculated', $metadata);
    }

    public static function notified(): Outcome
    {
        return new Outcome('notified');
    }

    public static function withValue(string $value, array $metadata = []): Outcome
    {
        return new Outcome($value, $metadata);
    }
}
