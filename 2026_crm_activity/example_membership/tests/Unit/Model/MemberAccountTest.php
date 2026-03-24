<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityType;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\MemberAccount;
use App\Membership\Model\MemberId;
use App\Membership\Model\Points\InsufficientPointsException;
use App\Membership\Model\Reaction\MembershipReactions;
use App\Membership\Model\Reaction\ReactionRules;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemberAccountTest extends TestCase
{
    private function rules(): ReactionRules
    {
        return MembershipReactions::standard();
    }

    private function givenMember(): MemberAccount
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan Kowalski', $this->rules());
        $account->releaseEvents();
        return $account;
    }

    private function givenMemberWithPoints(int $points): MemberAccount
    {
        $account = $this->givenMember();
        $account->record(new Activity(ActivityType::PurchaseInStore, [
            'amount' => $points, 'currency' => 'PLN', 'store_id' => 'SETUP', 'transaction_id' => 'TXN-SETUP',
        ]));
        $account->releaseEvents();
        return $account;
    }

    // --- Opening ---

    #[Test]
    public function opening_account_emits_member_opened_event(): void
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan Kowalski', $this->rules());

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberOpened::class, $events[0]);
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

        $outcome = $account->record(new Activity(ActivityType::PurchaseInStore, [
            'amount' => 150.00, 'currency' => 'PLN', 'store_id' => 'STORE-01', 'transaction_id' => 'TXN-123',
        ]));

        self::assertSame(OutcomeType::PointsEarned, $outcome->type);
        self::assertSame(150, $outcome->details['points']);
        self::assertSame(150, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    #[Test]
    public function in_store_purchase_emits_points_earned(): void
    {
        $account = $this->givenMember();

        $account->record(new Activity(ActivityType::PurchaseInStore, [
            'amount' => 200, 'currency' => 'PLN', 'store_id' => 'S1', 'transaction_id' => 'T1',
        ]));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsEarned::class, $events[0]);
        self::assertSame(200, $events[0]->points);
    }

    // --- Online purchase (pending) ---

    #[Test]
    public function online_purchase_earns_pending_points_with_multiplier(): void
    {
        $account = $this->givenMember();

        $outcome = $account->record(new Activity(ActivityType::OnlinePurchase, [
            'amount' => 200.00, 'currency' => 'PLN', 'order_id' => 'ORD-001',
        ]));

        self::assertSame(OutcomeType::PointsPending, $outcome->type);
        self::assertSame(300, $outcome->details['points']); // 200 * 1.5
        self::assertSame(0, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance());
    }

    #[Test]
    public function online_purchase_emits_points_pending(): void
    {
        $account = $this->givenMember();

        $account->record(new Activity(ActivityType::OnlinePurchase, [
            'amount' => 100, 'currency' => 'PLN', 'order_id' => 'ORD-002',
        ]));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsPending::class, $events[0]);
        self::assertSame('ORD-002', $events[0]->awaitingReference);
    }

    // --- Delivery (activates pending) ---

    #[Test]
    public function delivery_activates_pending_points(): void
    {
        $account = $this->givenMember();
        $account->record(new Activity(ActivityType::OnlinePurchase, [
            'amount' => 200, 'currency' => 'PLN', 'order_id' => 'ORD-001',
        ]));
        $account->releaseEvents();

        $outcome = $account->record(new Activity(ActivityType::PackageDelivered, [
            'order_id' => 'ORD-001',
        ]));

        self::assertSame(OutcomeType::PointsActivated, $outcome->type);
        self::assertSame(300, $outcome->details['points']);
        self::assertSame(300, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    #[Test]
    public function delivery_emits_points_activated(): void
    {
        $account = $this->givenMember();
        $account->record(new Activity(ActivityType::OnlinePurchase, [
            'amount' => 100, 'currency' => 'PLN', 'order_id' => 'ORD-003',
        ]));
        $account->releaseEvents();

        $account->record(new Activity(ActivityType::PackageDelivered, ['order_id' => 'ORD-003']));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsActivated::class, $events[0]);
    }

    #[Test]
    public function delivery_for_unknown_order_throws(): void
    {
        $account = $this->givenMember();

        $this->expectException(\DomainException::class);
        $account->record(new Activity(ActivityType::PackageDelivered, ['order_id' => 'ORD-UNKNOWN']));
    }

    // --- Challenge ---

    #[Test]
    public function challenge_completed_earns_bonus_points(): void
    {
        $account = $this->givenMember();

        $outcome = $account->record(new Activity(ActivityType::ChallengeCompleted, [
            'challenge_id' => 'SUMMER-2026', 'bonus_points' => 500,
        ]));

        self::assertSame(OutcomeType::PointsEarned, $outcome->type);
        self::assertSame(500, $account->activeBalance());
    }

    // --- Birthday ---

    #[Test]
    public function birthday_bonus_earns_points(): void
    {
        $account = $this->givenMember();

        $account->record(new Activity(ActivityType::BirthdayBonus, ['bonus_points' => 100]));

        self::assertSame(100, $account->activeBalance());
    }

    // --- Reward redemption ---

    #[Test]
    public function redeeming_reward_spends_points(): void
    {
        $account = $this->givenMemberWithPoints(2_000);

        $outcome = $account->record(new Activity(ActivityType::RewardRedemption, [
            'reward_id' => 'RWD-10PCT', 'reward_name' => '10% Discount', 'points_cost' => 1_000,
        ]));

        self::assertSame(OutcomeType::PointsSpent, $outcome->type);
        self::assertSame(1_000, $account->activeBalance());
    }

    #[Test]
    public function redeeming_reward_emits_points_spent(): void
    {
        $account = $this->givenMemberWithPoints(2_000);

        $account->record(new Activity(ActivityType::RewardRedemption, [
            'reward_id' => 'RWD-10PCT', 'reward_name' => '10% Discount', 'points_cost' => 1_000,
        ]));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsSpent::class, $events[0]);
    }

    #[Test]
    public function redeeming_with_insufficient_points_throws(): void
    {
        $account = $this->givenMemberWithPoints(500);

        $this->expectException(InsufficientPointsException::class);
        $account->record(new Activity(ActivityType::RewardRedemption, [
            'reward_id' => 'RWD-10PCT', 'points_cost' => 1_000,
        ]));
    }

    // --- Activity log ---

    #[Test]
    public function activities_preserve_order_and_outcomes(): void
    {
        $account = $this->givenMember();

        $account->record(new Activity(ActivityType::PurchaseInStore, ['amount' => 100, 'currency' => 'PLN', 'store_id' => 'S1', 'transaction_id' => 'T1']));
        $account->record(new Activity(ActivityType::ChallengeCompleted, ['challenge_id' => 'CH-1', 'bonus_points' => 200]));
        $account->record(new Activity(ActivityType::BirthdayBonus, ['bonus_points' => 50]));

        $activities = $account->activities();
        self::assertCount(3, $activities);

        $types = array_map(fn($a) => $a->type, $activities);
        self::assertSame([ActivityType::PurchaseInStore, ActivityType::ChallengeCompleted, ActivityType::BirthdayBonus], $types);

        // Each activity has its outcome attached
        foreach ($activities as $a) {
            self::assertNotNull($a->outcome());
        }
    }

    // --- Full lifecycle ---

    #[Test]
    public function full_lifecycle_scenario(): void
    {
        $account = $this->givenMember();

        // In-store purchase
        $account->record(new Activity(ActivityType::PurchaseInStore, ['amount' => 300, 'currency' => 'PLN', 'store_id' => 'S1', 'transaction_id' => 'T1']));
        self::assertSame(300, $account->activeBalance());

        // Online order (pending)
        $account->record(new Activity(ActivityType::OnlinePurchase, ['amount' => 200, 'currency' => 'PLN', 'order_id' => 'ORD-1']));
        self::assertSame(300, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance());

        // Challenge
        $account->record(new Activity(ActivityType::ChallengeCompleted, ['challenge_id' => 'SUMMER', 'bonus_points' => 500]));
        self::assertSame(800, $account->activeBalance());

        // Delivery
        $account->record(new Activity(ActivityType::PackageDelivered, ['order_id' => 'ORD-1']));
        self::assertSame(1_100, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());

        // Redeem
        $account->record(new Activity(ActivityType::RewardRedemption, ['reward_id' => 'R1', 'points_cost' => 1_000]));
        self::assertSame(100, $account->activeBalance());

        self::assertCount(5, $account->activities());
    }
}
