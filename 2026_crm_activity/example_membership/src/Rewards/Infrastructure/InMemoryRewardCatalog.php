<?php

declare(strict_types=1);

namespace App\Rewards\Infrastructure;

use App\Rewards\Domain\Reward;
use App\Rewards\Domain\RewardCatalog;
use App\Rewards\Domain\RewardType;

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

    private function seedCatalog(): void
    {
        $rewards = [
            new Reward('RWD-COFFEE', 'Free Coffee', 500, RewardType::FreeProduct, 'One free coffee at any partner café'),
            new Reward('RWD-10PCT', '10% Discount Coupon', 1_000, RewardType::Coupon, '10% off your next purchase'),
            new Reward('RWD-20PCT', '20% Discount Coupon', 2_000, RewardType::Coupon, '20% off your next purchase'),
            new Reward('RWD-50PLN', '50 PLN Voucher', 5_000, RewardType::Discount, '50 PLN off any purchase over 200 PLN'),
            new Reward('RWD-VIP', 'VIP Experience', 15_000, RewardType::Experience, 'Exclusive VIP shopping evening'),
            new Reward('RWD-INACTIVE', 'Discontinued Reward', 100, RewardType::FreeProduct, 'No longer available', false),
        ];

        foreach ($rewards as $reward) {
            $this->rewards[$reward->id] = $reward;
        }
    }
}
