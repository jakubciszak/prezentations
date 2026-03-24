<?php

declare(strict_types=1);

namespace App\Rewards\Domain;

enum RewardType: string
{
    case Coupon = 'coupon';
    case Discount = 'discount';
    case FreeProduct = 'free_product';
    case Experience = 'experience';
}
