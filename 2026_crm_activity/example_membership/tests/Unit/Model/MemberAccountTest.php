<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Event\RewardRedeemed;
use App\Membership\Model\Activity\ActivityType;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\MemberAccount;
use App\Membership\Model\MemberId;
use App\Membership\Model\Points\InsufficientPointsException;
use App\Membership\Model\Reward\Reward;
use App\Membership\Model\Reward\RewardType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemberAccountTest extends TestCase
{
    private function givenMember(): MemberAccount
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan Kowalski');
        $account->releaseEvents(); // clear MemberOpened
        return $account;
    }

    private function givenMemberWithPoints(int $points): MemberAccount
    {
        $account = $this->givenMember();
        $account->recordPurchase($points, 'PLN', 'STORE-01', 'TXN-SETUP');
        $account->releaseEvents();
        return $account;
    }

    private function givenCouponReward(): Reward
    {
        return new Reward('RWD-10PCT', '10% Discount', 1_000, RewardType::Coupon, '10% off');
    }

    // --- Opening ---

    #[Test]
    public function opening_account_emits_member_opened_event(): void
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan Kowalski');

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberOpened::class, $events[0]);
        self::assertSame('MBR-001', $events[0]->memberId);
    }

    #[Test]
    public function new_account_has_zero_balance(): void
    {
        $account = $this->givenMember();

        self::assertSame(0, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    // --- In-store purchase ---

    #[Test]
    public function in_store_purchase_earns_immediate_points(): void
    {
        $account = $this->givenMember();

        $activity = $account->recordPurchase(150.00, 'PLN', 'STORE-WAW-01', 'TXN-123');

        self::assertSame(ActivityType::PurchaseInStore, $activity->type);
        self::assertSame(OutcomeType::PointsEarned, $activity->outcome->type);
        self::assertSame(150, $activity->outcome->details['points']);
        self::assertSame(150, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    #[Test]
    public function in_store_purchase_records_participants(): void
    {
        $account = $this->givenMember();

        $activity = $account->recordPurchase(100, 'PLN', 'STORE-KRK-01', 'TXN-456');

        self::assertSame('MBR-001', $activity->participants['customer']);
        self::assertSame('STORE-KRK-01', $activity->participants['store']);
    }

    #[Test]
    public function in_store_purchase_emits_points_earned(): void
    {
        $account = $this->givenMember();
        $account->recordPurchase(200, 'PLN', 'STORE-01', 'TXN-789');

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsEarned::class, $events[0]);
        self::assertSame(200, $events[0]->points);
    }

    // --- Online purchase (pending points) ---

    #[Test]
    public function online_purchase_earns_pending_points_with_bonus(): void
    {
        $account = $this->givenMember();

        $activity = $account->recordOnlinePurchase(200.00, 'PLN', 'ORD-001');

        self::assertSame(ActivityType::OnlinePurchase, $activity->type);
        self::assertSame(OutcomeType::PointsPending, $activity->outcome->type);
        self::assertSame(300, $activity->outcome->details['points']); // 200 * 1.5
        self::assertSame(0, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance());
    }

    #[Test]
    public function online_purchase_emits_points_pending(): void
    {
        $account = $this->givenMember();
        $account->recordOnlinePurchase(100, 'PLN', 'ORD-002');

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsPending::class, $events[0]);
        self::assertSame('ORD-002', $events[0]->awaitingReference);
    }

    // --- Delivery (activates pending points) ---

    #[Test]
    public function delivery_activates_pending_points(): void
    {
        $account = $this->givenMember();
        $account->recordOnlinePurchase(200, 'PLN', 'ORD-001');
        $account->releaseEvents();

        $activity = $account->recordDelivery('ORD-001');

        self::assertSame(ActivityType::PackageDelivered, $activity->type);
        self::assertSame(OutcomeType::PointsActivated, $activity->outcome->type);
        self::assertSame(300, $activity->outcome->details['points']);
        self::assertSame(300, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    #[Test]
    public function delivery_emits_points_activated(): void
    {
        $account = $this->givenMember();
        $account->recordOnlinePurchase(100, 'PLN', 'ORD-003');
        $account->releaseEvents();

        $account->recordDelivery('ORD-003');

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsActivated::class, $events[0]);
        self::assertSame('ORD-003', $events[0]->reference);
    }

    #[Test]
    public function delivery_for_unknown_order_throws(): void
    {
        $account = $this->givenMember();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("No pending points found for order 'ORD-UNKNOWN'");
        $account->recordDelivery('ORD-UNKNOWN');
    }

    // --- Challenge ---

    #[Test]
    public function challenge_completed_earns_bonus_points(): void
    {
        $account = $this->givenMember();

        $activity = $account->recordChallengeCompleted('SUMMER-2026', 500);

        self::assertSame(ActivityType::ChallengeCompleted, $activity->type);
        self::assertSame(500, $account->activeBalance());
    }

    // --- Birthday bonus ---

    #[Test]
    public function birthday_bonus_earns_points(): void
    {
        $account = $this->givenMember();

        $activity = $account->recordBirthdayBonus(100);

        self::assertSame(ActivityType::BirthdayBonus, $activity->type);
        self::assertSame(100, $account->activeBalance());
    }

    // --- Reward redemption ---

    #[Test]
    public function redeeming_reward_spends_points_and_creates_redemption(): void
    {
        $account = $this->givenMemberWithPoints(2_000);
        $reward = $this->givenCouponReward();

        $activity = $account->redeemReward($reward);

        self::assertSame(ActivityType::RewardRedemption, $activity->type);
        self::assertSame(OutcomeType::RewardIssued, $activity->outcome->type);
        self::assertSame(1_000, $account->activeBalance());
        self::assertCount(1, $account->redemptions());
        self::assertSame('RWD-10PCT', $account->redemptions()[0]->reward->id);
    }

    #[Test]
    public function redeeming_reward_emits_points_spent_and_reward_redeemed(): void
    {
        $account = $this->givenMemberWithPoints(2_000);

        $account->redeemReward($this->givenCouponReward());

        $events = $account->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(PointsSpent::class, $events[0]);
        self::assertInstanceOf(RewardRedeemed::class, $events[1]);
        self::assertSame('RWD-10PCT', $events[1]->rewardId);
    }

    #[Test]
    public function redeeming_reward_with_insufficient_points_throws(): void
    {
        $account = $this->givenMemberWithPoints(500);

        $this->expectException(InsufficientPointsException::class);
        $account->redeemReward($this->givenCouponReward());
    }

    #[Test]
    public function redeeming_inactive_reward_throws(): void
    {
        $account = $this->givenMemberWithPoints(1_000);
        $inactive = new Reward('RWD-OLD', 'Old', 100, RewardType::FreeProduct, 'Gone', false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("not active");
        $account->redeemReward($inactive);
    }

    // --- Activities history ---

    #[Test]
    public function activities_are_recorded_in_order(): void
    {
        $account = $this->givenMember();
        $account->recordPurchase(100, 'PLN', 'S1', 'T1');
        $account->recordChallengeCompleted('CH-1', 200);
        $account->recordBirthdayBonus(50);

        $types = array_map(fn($a) => $a->type, $account->activities());
        self::assertSame([
            ActivityType::PurchaseInStore,
            ActivityType::ChallengeCompleted,
            ActivityType::BirthdayBonus,
        ], $types);
    }

    // --- Mixed scenario ---

    #[Test]
    public function full_lifecycle_scenario(): void
    {
        $account = $this->givenMember();

        // Purchase in store
        $account->recordPurchase(300, 'PLN', 'STORE-01', 'TXN-1');
        self::assertSame(300, $account->activeBalance());

        // Online order (pending)
        $account->recordOnlinePurchase(200, 'PLN', 'ORD-1');
        self::assertSame(300, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance()); // 200 * 1.5

        // Challenge bonus
        $account->recordChallengeCompleted('SUMMER', 500);
        self::assertSame(800, $account->activeBalance());

        // Deliver the package
        $account->recordDelivery('ORD-1');
        self::assertSame(1_100, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());

        // Redeem a reward
        $account->redeemReward($this->givenCouponReward());
        self::assertSame(100, $account->activeBalance());

        self::assertCount(5, $account->activities());
        self::assertCount(1, $account->redemptions());
    }
}
