<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Rewards\Model\Redemption;
use App\Rewards\Model\RedemptionStatus;
use App\Rewards\Model\Reward;
use App\Rewards\Model\RewardType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RewardRedemptionTest extends TestCase
{
    private function givenCouponReward(): Reward
    {
        return new Reward('RWD-10PCT', '10% Discount', 1_000, RewardType::Coupon, '10% off');
    }

    #[Test]
    public function new_redemption_is_pending(): void
    {
        $redemption = new Redemption('MBR-001', $this->givenCouponReward(), 1_000);

        self::assertSame(RedemptionStatus::Pending, $redemption->status());
        self::assertSame('MBR-001', $redemption->memberId);
        self::assertSame(1_000, $redemption->pointsSpent);
        self::assertSame('RWD-10PCT', $redemption->reward->id);
        self::assertNotEmpty($redemption->id);
    }

    #[Test]
    public function pending_redemption_can_be_confirmed(): void
    {
        $redemption = new Redemption('MBR-001', $this->givenCouponReward(), 1_000);

        $redemption->confirm();

        self::assertSame(RedemptionStatus::Confirmed, $redemption->status());
    }

    #[Test]
    public function pending_redemption_can_be_cancelled(): void
    {
        $redemption = new Redemption('MBR-001', $this->givenCouponReward(), 1_000);

        $redemption->cancel();

        self::assertSame(RedemptionStatus::Cancelled, $redemption->status());
    }

    #[Test]
    public function confirmed_redemption_cannot_be_confirmed_again(): void
    {
        $redemption = new Redemption('MBR-001', $this->givenCouponReward(), 1_000);
        $redemption->confirm();

        $this->expectException(\DomainException::class);
        $redemption->confirm();
    }

    #[Test]
    public function cancelled_redemption_cannot_be_confirmed(): void
    {
        $redemption = new Redemption('MBR-001', $this->givenCouponReward(), 1_000);
        $redemption->cancel();

        $this->expectException(\DomainException::class);
        $redemption->confirm();
    }

    #[Test]
    public function reward_types_cover_all_variants(): void
    {
        self::assertSame('coupon', RewardType::Coupon->value);
        self::assertSame('discount', RewardType::Discount->value);
        self::assertSame('free_product', RewardType::FreeProduct->value);
        self::assertSame('experience', RewardType::Experience->value);
    }

    #[Test]
    public function reward_holds_all_properties(): void
    {
        $reward = new Reward('RWD-VIP', 'VIP Evening', 15_000, RewardType::Experience, 'Exclusive event', true);

        self::assertSame('RWD-VIP', $reward->id);
        self::assertSame('VIP Evening', $reward->name);
        self::assertSame(15_000, $reward->pointsCost);
        self::assertSame(RewardType::Experience, $reward->type);
        self::assertTrue($reward->active);
    }

    #[Test]
    public function inactive_reward(): void
    {
        $reward = new Reward('RWD-OLD', 'Old Reward', 100, RewardType::FreeProduct, 'Gone', false);

        self::assertFalse($reward->active);
    }
}
