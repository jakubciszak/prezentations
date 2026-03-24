<?php

declare(strict_types=1);

namespace App\Membership\Model\Reaction;

use App\Membership\Model\Activity\ActivityType;

/**
 * Declares the reaction rules for the loyalty membership program.
 *
 * This is the single place where the business logic lives:
 * "when X happens, do Y with points". No procedural code —
 * just declarations of cause and effect.
 */
final class MembershipReactions
{
    public static function standard(): ReactionRules
    {
        return new ReactionRules(
            ReactionRule::earn(
                on: ActivityType::PurchaseInStore,
                amount: fn($a) => (int) floor($a->get('amount')),
            ),

            ReactionRule::earnPending(
                on: ActivityType::OnlinePurchase,
                amount: fn($a) => (int) floor($a->get('amount') * 1.5),
                reference: fn($a) => $a->get('order_id'),
            ),

            ReactionRule::activate(
                on: ActivityType::PackageDelivered,
                reference: fn($a) => $a->get('order_id'),
            ),

            ReactionRule::earnBonus(
                on: ActivityType::ChallengeCompleted,
                amount: fn($a) => $a->get('bonus_points'),
            ),

            ReactionRule::earnBonus(
                on: ActivityType::BirthdayBonus,
                amount: fn($a) => $a->get('bonus_points'),
            ),

            ReactionRule::spend(
                on: ActivityType::RewardRedemption,
                amount: fn($a) => $a->get('points_cost'),
            ),
        );
    }
}
