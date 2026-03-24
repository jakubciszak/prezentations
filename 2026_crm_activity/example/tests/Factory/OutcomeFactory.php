<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Onboarding\Model\Outcome;

final class OutcomeFactory
{
    public static function completed(array $metadata = []): Outcome
    {
        return new Outcome('completed', $metadata);
    }

    public static function clean(array $metadata = []): Outcome
    {
        return new Outcome('clean', $metadata);
    }

    public static function flagged(): Outcome
    {
        return new Outcome('flagged');
    }

    public static function approved(array $metadata = []): Outcome
    {
        return new Outcome('approved', $metadata);
    }

    public static function rejected(): Outcome
    {
        return new Outcome('rejected');
    }

    public static function passed(): Outcome
    {
        return new Outcome('passed');
    }

    public static function failed(): Outcome
    {
        return new Outcome('failed');
    }

    public static function lowRisk(): Outcome
    {
        return new Outcome('low_risk');
    }

    public static function highRisk(): Outcome
    {
        return new Outcome('high_risk');
    }

    public static function expired(): Outcome
    {
        return new Outcome('expired');
    }

    public static function timeout(): Outcome
    {
        return new Outcome('timeout');
    }

    public static function withValue(string $value, array $metadata = []): Outcome
    {
        return new Outcome($value, $metadata);
    }
}
