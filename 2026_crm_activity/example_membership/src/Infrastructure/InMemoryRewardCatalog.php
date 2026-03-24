<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Rewards\Model\Reward;
use App\Rewards\Model\RewardCatalog;
use App\Rewards\Model\RewardType;

final class InMemoryRewardCatalog implements RewardCatalog
{
    /** @var array<string, Reward> */
    private array $rewards = [];

    public function __construct()
    {
        $this->seedCatalog();
    }

    public function findById(string $rewardId): ?Reward
    {
        return $this->rewards[$rewardId] ?? null;
    }

    /** @return Reward[] */
    public function findAvailableForPoints(int $points): array
    {
        return array_values(array_filter(
            $this->rewards,
            fn(Reward $r) => $r->active && $r->pointsCost <= $points,
        ));
    }

    /** @return Reward[] */
    public function all(): array
    {
        return array_values($this->rewards);
    }

    private function seedCatalog(): void
    {
        $rewards = [
            new Reward('RWD-COFFEE', 'Free Coffee', 500, RewardType::FreeProduct, 'One free coffee at any partner café'),
            new Reward('RWD-10PCT', '10% Discount Coupon', 1_000, RewardType::Coupon, '10% off your next purchase'),
            new Reward('RWD-20PCT', '20% Discount Coupon', 2_000, RewardType::Coupon, '20% off your next purchase'),
            new Reward('RWD-50PLN', '50 PLN Voucher', 5_000, RewardType::Discount, '50 PLN off any purchase over 200 PLN'),
            new Reward('RWD-TSHIRT', 'Branded T-Shirt', 3_000, RewardType::FreeProduct, 'Exclusive loyalty program T-shirt'),
            new Reward('RWD-VIP', 'VIP Experience', 15_000, RewardType::Experience, 'Exclusive VIP shopping evening with personal stylist'),
            new Reward('RWD-AUTO-VOUCHER', 'Auto Purchase Voucher', 0, RewardType::Coupon, 'Auto-assigned voucher for qualifying purchases', true),
            new Reward('RWD-INACTIVE', 'Discontinued Reward', 100, RewardType::FreeProduct, 'No longer available', false),
        ];

        foreach ($rewards as $reward) {
            $this->rewards[$reward->id] = $reward;
        }
    }
}
