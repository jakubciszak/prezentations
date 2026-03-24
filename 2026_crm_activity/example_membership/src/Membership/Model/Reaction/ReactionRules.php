<?php

declare(strict_types=1);

namespace App\Membership\Model\Reaction;

use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityType;

/**
 * Collection of reaction rules.
 *
 * Acts as a registry: given an Activity, it finds the matching rule.
 * Different MemberAccounts could have different rule sets
 * (e.g., VIP members with different multipliers).
 */
final readonly class ReactionRules
{
    /** @var array<string, ReactionRule> keyed by ActivityType::value */
    private array $rules;

    public function __construct(ReactionRule ...$rules)
    {
        $indexed = [];
        foreach ($rules as $rule) {
            $indexed[$rule->activityType->value] = $rule;
        }
        $this->rules = $indexed;
    }

    public function findFor(Activity $activity): ReactionRule
    {
        return $this->rules[$activity->type->value]
            ?? throw new \DomainException("No reaction rule defined for activity type '{$activity->type->value}'");
    }

    public function has(ActivityType $type): bool
    {
        return isset($this->rules[$type->value]);
    }
}
