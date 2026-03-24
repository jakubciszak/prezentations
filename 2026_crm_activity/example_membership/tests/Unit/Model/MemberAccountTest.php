<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Infrastructure\InMemoryRewardCatalog;
use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Model\Activity\Activity;
use App\Membership\Model\Activity\ActivityStatus;
use App\Membership\Model\Activity\ActivityType;
use App\Membership\Model\Activity\OutcomeType;
use App\Membership\Model\Flow\MembershipFlows;
use App\Membership\Model\MemberAccount;
use App\Membership\Model\MemberId;
use App\Membership\Model\Points\InsufficientPointsException;
use App\Membership\Service\PointsActivationService;
use App\Membership\Service\PointsCalculationService;
use App\Membership\Service\RewardService;
use App\Membership\Service\ServiceRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemberAccountTest extends TestCase
{
    private ServiceRouter $router;

    protected function setUp(): void
    {
        $this->router = new ServiceRouter(
            MembershipFlows::standard(),
            [
                'points_calculation' => new PointsCalculationService(),
                'points_activation' => new PointsActivationService(),
                'reward_service' => new RewardService(new InMemoryRewardCatalog()),
            ],
        );
    }

    private function givenMember(): MemberAccount
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan Kowalski');
        $account->releaseEvents();
        return $account;
    }

    /**
     * Helper: record activity → dispatch to service → handle response.
     */
    private function process(MemberAccount $account, Activity $activity): void
    {
        $account->record($activity);
        $response = $this->router->dispatch($activity);
        $account->handleServiceResponse($response);
    }

    private function givenMemberWithPoints(int $points): MemberAccount
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::PurchaseInStore, [
            'amount' => $points, 'transaction_id' => 'TXN-SETUP',
        ]));
        $account->releaseEvents();
        return $account;
    }

    // --- Opening ---

    #[Test]
    public function opening_account_emits_member_opened(): void
    {
        $account = MemberAccount::open(MemberId::from('MBR-001'), 'Jan');
        $events = $account->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(MemberOpened::class, $events[0]);
    }

    #[Test]
    public function new_account_has_zero_balance(): void
    {
        self::assertSame(0, $this->givenMember()->activeBalance());
    }

    // --- Record + Service flow ---

    #[Test]
    public function recording_activity_does_not_change_balance(): void
    {
        $account = $this->givenMember();
        $account->record(new Activity(ActivityType::PurchaseInStore, ['amount' => 500, 'transaction_id' => 'T1']));

        self::assertSame(0, $account->activeBalance());
        self::assertSame(ActivityStatus::Recorded, $account->activities()[0]->status());
    }

    #[Test]
    public function handling_service_response_completes_activity_and_updates_ledger(): void
    {
        $account = $this->givenMember();
        $activity = new Activity(ActivityType::PurchaseInStore, ['amount' => 150, 'transaction_id' => 'T1']);

        $account->record($activity);
        $response = $this->router->dispatch($activity);
        $outcome = $account->handleServiceResponse($response);

        self::assertSame(OutcomeType::PointsEarned, $outcome->type);
        self::assertSame(150, $outcome->payload['points']);
        self::assertSame(150, $account->activeBalance());
        self::assertTrue($activity->isCompleted());
        self::assertNotNull($activity->outcome());
    }

    // --- In-store purchase ---

    #[Test]
    public function in_store_purchase_earns_immediate_points(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::PurchaseInStore, [
            'amount' => 250, 'transaction_id' => 'TXN-123',
        ]));

        self::assertSame(250, $account->activeBalance());
    }

    #[Test]
    public function in_store_purchase_emits_points_earned(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::PurchaseInStore, [
            'amount' => 200, 'transaction_id' => 'T1',
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
        $this->process($account, new Activity(ActivityType::OnlinePurchase, [
            'amount' => 200, 'order_id' => 'ORD-001',
        ]));

        self::assertSame(0, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance()); // 200 * 1.5
    }

    #[Test]
    public function online_purchase_emits_points_pending(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::OnlinePurchase, [
            'amount' => 100, 'order_id' => 'ORD-002',
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
        $this->process($account, new Activity(ActivityType::OnlinePurchase, [
            'amount' => 200, 'order_id' => 'ORD-001',
        ]));
        $account->releaseEvents();

        $this->process($account, new Activity(ActivityType::PackageDelivered, [
            'order_id' => 'ORD-001',
        ]));

        self::assertSame(300, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());
    }

    #[Test]
    public function delivery_emits_points_activated(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::OnlinePurchase, [
            'amount' => 100, 'order_id' => 'ORD-003',
        ]));
        $account->releaseEvents();

        $this->process($account, new Activity(ActivityType::PackageDelivered, [
            'order_id' => 'ORD-003',
        ]));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsActivated::class, $events[0]);
    }

    #[Test]
    public function delivery_for_unknown_order_throws(): void
    {
        $account = $this->givenMember();
        $activity = new Activity(ActivityType::PackageDelivered, ['order_id' => 'ORD-UNKNOWN']);
        $account->record($activity);
        $response = $this->router->dispatch($activity);

        $this->expectException(\DomainException::class);
        $account->handleServiceResponse($response);
    }

    // --- Challenge ---

    #[Test]
    public function challenge_completed_earns_bonus(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::ChallengeCompleted, [
            'challenge_id' => 'SUMMER-2026', 'bonus_points' => 500,
        ]));

        self::assertSame(500, $account->activeBalance());
    }

    // --- Birthday ---

    #[Test]
    public function birthday_bonus_earns_points(): void
    {
        $account = $this->givenMember();
        $this->process($account, new Activity(ActivityType::BirthdayBonus, [
            'bonus_points' => 100,
        ]));

        self::assertSame(100, $account->activeBalance());
    }

    // --- Reward redemption ---

    #[Test]
    public function redeeming_reward_spends_points(): void
    {
        $account = $this->givenMemberWithPoints(2_000);

        $this->process($account, new Activity(ActivityType::RewardRedemption, [
            'reward_id' => 'RWD-10PCT',
        ]));

        self::assertSame(1_000, $account->activeBalance());
    }

    #[Test]
    public function redeeming_reward_emits_points_spent(): void
    {
        $account = $this->givenMemberWithPoints(2_000);

        $this->process($account, new Activity(ActivityType::RewardRedemption, [
            'reward_id' => 'RWD-10PCT',
        ]));

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsSpent::class, $events[0]);
    }

    #[Test]
    public function redeeming_with_insufficient_points_throws(): void
    {
        $account = $this->givenMemberWithPoints(500);
        $activity = new Activity(ActivityType::RewardRedemption, ['reward_id' => 'RWD-10PCT']);
        $account->record($activity);
        $response = $this->router->dispatch($activity);

        $this->expectException(InsufficientPointsException::class);
        $account->handleServiceResponse($response);
    }

    // --- Activity log ---

    #[Test]
    public function activities_are_recorded_with_outcomes(): void
    {
        $account = $this->givenMember();

        $this->process($account, new Activity(ActivityType::PurchaseInStore, ['amount' => 100, 'transaction_id' => 'T1']));
        $this->process($account, new Activity(ActivityType::ChallengeCompleted, ['challenge_id' => 'CH', 'bonus_points' => 200]));
        $this->process($account, new Activity(ActivityType::BirthdayBonus, ['bonus_points' => 50]));

        $activities = $account->activities();
        self::assertCount(3, $activities);

        foreach ($activities as $a) {
            self::assertTrue($a->isCompleted());
            self::assertNotNull($a->outcome());
        }
    }

    // --- Full lifecycle ---

    #[Test]
    public function full_lifecycle_scenario(): void
    {
        $account = $this->givenMember();

        // In-store
        $this->process($account, new Activity(ActivityType::PurchaseInStore, ['amount' => 300, 'transaction_id' => 'T1']));
        self::assertSame(300, $account->activeBalance());

        // Online (pending)
        $this->process($account, new Activity(ActivityType::OnlinePurchase, ['amount' => 200, 'order_id' => 'ORD-1']));
        self::assertSame(300, $account->activeBalance());
        self::assertSame(300, $account->pendingBalance());

        // Challenge
        $this->process($account, new Activity(ActivityType::ChallengeCompleted, ['challenge_id' => 'SUMMER', 'bonus_points' => 500]));
        self::assertSame(800, $account->activeBalance());

        // Delivery
        $this->process($account, new Activity(ActivityType::PackageDelivered, ['order_id' => 'ORD-1']));
        self::assertSame(1_100, $account->activeBalance());
        self::assertSame(0, $account->pendingBalance());

        // Redeem
        $this->process($account, new Activity(ActivityType::RewardRedemption, ['reward_id' => 'RWD-10PCT']));
        self::assertSame(100, $account->activeBalance());

        self::assertCount(5, $account->activities());
    }
}
