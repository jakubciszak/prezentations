<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Infrastructure\InMemoryRewardCatalog;
use App\Membership\Model\MemberAccount;
use App\Membership\Model\MemberId;
use App\Membership\Model\Points\InsufficientPointsException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

final class MembershipContext implements Context
{
    private SharedState $state;

    public function __construct()
    {
        $this->state = SharedState::getInstance();
    }

    #[BeforeScenario]
    public function resetState(BeforeScenarioScope $scope): void
    {
        SharedState::reset();
        $this->state = SharedState::getInstance();
        $this->state->rewardCatalog = new InMemoryRewardCatalog();
    }

    #[Given('a member :name with id :memberId')]
    public function aMember(string $name, string $memberId): void
    {
        $this->state->currentAccount = MemberAccount::open(MemberId::from($memberId), $name);
    }

    #[Given('the member has earned :points points from purchases')]
    public function theMemberHasEarnedPoints(int $points): void
    {
        $this->state->currentAccount->recordPurchase($points, 'PLN', 'SETUP-STORE', 'TXN-SETUP');
    }

    #[When('the member makes an in-store purchase of :amount PLN at store :storeId with transaction :txnId')]
    public function theMemberMakesPurchase(int $amount, string $storeId, string $txnId): void
    {
        $this->state->currentAccount->recordPurchase($amount, 'PLN', $storeId, $txnId);
    }

    #[When('the member makes an online purchase of :amount PLN with order :orderId')]
    public function theMemberMakesOnlinePurchase(int $amount, string $orderId): void
    {
        $this->state->currentAccount->recordOnlinePurchase($amount, 'PLN', $orderId);
    }

    #[When('the package for order :orderId is delivered')]
    public function thePackageIsDelivered(string $orderId): void
    {
        $this->state->currentAccount->recordDelivery($orderId);
    }

    #[When('the member completes challenge :challengeId earning :points bonus points')]
    public function theMemberCompletesChallenge(string $challengeId, int $points): void
    {
        $this->state->currentAccount->recordChallengeCompleted($challengeId, $points);
    }

    #[When('the member receives a birthday bonus of :points points')]
    public function theMemberReceivesBirthdayBonus(int $points): void
    {
        $this->state->currentAccount->recordBirthdayBonus($points);
    }

    #[When('the member redeems reward :rewardId')]
    public function theMemberRedeemsReward(string $rewardId): void
    {
        $reward = $this->state->rewardCatalog->findById($rewardId);
        assert($reward !== null, "Reward '{$rewardId}' not found in catalog");
        $this->state->currentAccount->redeemReward($reward);
    }

    #[Then('the member should have :points active points')]
    public function theMemberShouldHaveActivePoints(int $points): void
    {
        $actual = $this->state->currentAccount->activeBalance();
        assert($actual === $points, "Expected {$points} active points, got {$actual}");
    }

    #[Then('the member should have :points pending points')]
    public function theMemberShouldHavePendingPoints(int $points): void
    {
        $actual = $this->state->currentAccount->pendingBalance();
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
        $actual = count($this->state->currentAccount->redemptions());
        assert($actual === $count, "Expected {$count} redemptions, got {$actual}");
    }

    #[Then('redeeming reward :rewardId should fail with insufficient points')]
    public function redeemingRewardShouldFail(string $rewardId): void
    {
        $reward = $this->state->rewardCatalog->findById($rewardId);
        assert($reward !== null, "Reward '{$rewardId}' not found in catalog");

        try {
            $this->state->currentAccount->redeemReward($reward);
            assert(false, 'Expected InsufficientPointsException was not thrown');
        } catch (InsufficientPointsException) {
            // expected
        }
    }
}
