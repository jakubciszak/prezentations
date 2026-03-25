<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

enum ActivityType: string
{
    case PurchaseInStore = 'purchase_in_store';
    case OnlinePurchase = 'online_purchase';
    case PackageDelivered = 'package_delivered';
    case ChallengeCompleted = 'challenge_completed';
    case RewardRedemption = 'reward_redemption';
    case BirthdayBonus = 'birthday_bonus';
}
