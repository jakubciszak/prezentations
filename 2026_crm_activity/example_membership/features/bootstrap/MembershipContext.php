<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\MembershipActivity\Application\Adapter\PointsActivationAdapter;
use App\MembershipActivity\Application\Adapter\PointsActivityAdapter;
use App\MembershipActivity\Application\Adapter\RewardsActivityAdapter;
use App\MembershipActivity\Application\Flow\MembershipFlows;
use App\MembershipActivity\Application\ServiceRouter;
use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\ActivityType;
use App\MembershipActivity\Domain\MemberAccount;
use App\MembershipActivity\Domain\MemberId;
use App\Points\Api\WalletFacade;
use App\Points\Application\DefaultPointsFacade;
use App\Points\Application\DefaultWalletService;
use App\Points\Domain\InsufficientPointsException;
use App\Rewards\Application\DefaultRewardsFacade;
use App\Rewards\Infrastructure\InMemoryRewardCatalog;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

final class MembershipContext implements Context
{
    private SharedState $state;
    private ServiceRouter $router;
    private WalletFacade $wallet;

    public function __construct()
    {
        $this->state = SharedState::getInstance();
    }

    #[BeforeScenario]
    public function resetState(BeforeScenarioScope $scope): void
    {
        SharedState::reset();
        $this->state = SharedState::getInstance();

        $pointsFacade = new DefaultPointsFacade();
        $this->wallet = new DefaultWalletService();
        $rewardsFacade = new DefaultRewardsFacade(new InMemoryRewardCatalog());

        $this->router = new ServiceRouter(
            MembershipFlows::standard(),
            [
                'points_calculation' => new PointsActivityAdapter($pointsFacade, $this->wallet),
                'points_activation' => new PointsActivationAdapter($pointsFacade, $this->wallet),
                'reward_service' => new RewardsActivityAdapter($rewardsFacade, $this->wallet),
            ],
        );
    }

    private function process(Activity $activity): void
    {
        $account = $this->state->currentAccount;
        $account->record($activity);
        $outcome = $this->router->dispatch($activity, $account->id->value);
        $account->handleOutcome($activity->id->value, $outcome);
    }

    #[Given('a member :name with id :memberId')]
    public function aMember(string $name, string $memberId): void
    {
        $this->state->currentAccount = MemberAccount::open(MemberId::from($memberId), $name);
    }

    #[Given('the member has earned :points points from purchases')]
    public function theMemberHasEarnedPoints(int $points): void
    {
        $this->process(new Activity(ActivityType::PurchaseInStore, [
            'amount' => $points, 'transaction_id' => 'TXN-SETUP',
        ]));
    }

    #[When('the member makes an in-store purchase of :amount PLN at store :storeId with transaction :txnId')]
    public function theMemberMakesPurchase(int $amount, string $storeId, string $txnId): void
    {
        $this->process(new Activity(ActivityType::PurchaseInStore, [
            'amount' => $amount, 'store_id' => $storeId, 'transaction_id' => $txnId,
        ]));
    }

    #[When('the member makes an online purchase of :amount PLN with order :orderId')]
    public function theMemberMakesOnlinePurchase(int $amount, string $orderId): void
    {
        $this->process(new Activity(ActivityType::OnlinePurchase, [
            'amount' => $amount, 'order_id' => $orderId,
        ]));
    }

    #[When('the package for order :orderId is delivered')]
    public function thePackageIsDelivered(string $orderId): void
    {
        $this->process(new Activity(ActivityType::PackageDelivered, [
            'order_id' => $orderId,
        ]));
    }

    #[When('the member completes challenge :challengeId earning :points bonus points')]
    public function theMemberCompletesChallenge(string $challengeId, int $points): void
    {
        $this->process(new Activity(ActivityType::ChallengeCompleted, [
            'challenge_id' => $challengeId, 'bonus_points' => $points,
        ]));
    }

    #[When('the member receives a birthday bonus of :points points')]
    public function theMemberReceivesBirthdayBonus(int $points): void
    {
        $this->process(new Activity(ActivityType::BirthdayBonus, [
            'bonus_points' => $points,
        ]));
    }

    #[When('the member redeems reward :rewardId')]
    public function theMemberRedeemsReward(string $rewardId): void
    {
        $this->process(new Activity(ActivityType::RewardRedemption, [
            'reward_id' => $rewardId,
        ]));
    }

    #[Then('the member should have :points active points')]
    public function theMemberShouldHaveActivePoints(int $points): void
    {
        $balance = $this->wallet->getBalance($this->state->currentAccount->id->value);
        $actual = $balance->active;
        assert($actual === $points, "Expected {$points} active points, got {$actual}");
    }

    #[Then('the member should have :points pending points')]
    public function theMemberShouldHavePendingPoints(int $points): void
    {
        $balance = $this->wallet->getBalance($this->state->currentAccount->id->value);
        $actual = $balance->pending;
        assert($actual === $points, "Expected {$points} pending points, got {$actual}");
    }

    #[Then('the last activity should be of type :type')]
    public function theLastActivityShouldBeOfType(string $type): void
    {
        $activities = $this->state->currentAccount->activities();
        $last = end($activities);
        assert($last !== false, 'No activities recorded');
        assert($last->type->value === $type, "Expected type '{$type}', got '{$last->type->value}'");
    }

    #[Then('the member should have :count activities recorded')]
    public function theMemberShouldHaveActivitiesRecorded(int $count): void
    {
        $actual = count($this->state->currentAccount->activities());
        assert($actual === $count, "Expected {$count} activities, got {$actual}");
    }

    #[Then('the member should have :count redemption')]
    #[Then('the member should have :count redemptions')]
    public function theMemberShouldHaveRedemptions(int $count): void
    {
        $actual = count(array_filter(
            $this->state->currentAccount->activities(),
            fn($a) => $a->type === ActivityType::RewardRedemption && $a->isCompleted(),
        ));
        assert($actual === $count, "Expected {$count} redemptions, got {$actual}");
    }

    #[Then('redeeming reward :rewardId should fail with insufficient points')]
    public function redeemingRewardShouldFail(string $rewardId): void
    {
        $activity = new Activity(ActivityType::RewardRedemption, ['reward_id' => $rewardId]);

        try {
            $this->process($activity);
            assert(false, 'Expected InsufficientPointsException was not thrown');
        } catch (InsufficientPointsException) {
            // expected
        }
    }
}
