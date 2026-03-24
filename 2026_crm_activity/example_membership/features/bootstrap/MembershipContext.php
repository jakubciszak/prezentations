<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Kernel;
use App\Membership\Engine\MembershipEngine;
use App\Membership\Handler\CaseEventLogger;
use App\Membership\Model\CaseRepository;
use App\Points\Model\PointsAccount;
use App\Points\Model\PointsAccountRepository;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

final class MembershipContext implements Context
{
    private SharedMembershipState $state;
    private string $activityType;

    public function __construct()
    {
        $this->state = SharedMembershipState::getInstance();
    }

    #[BeforeScenario]
    public function resetState(BeforeScenarioScope $scope): void
    {
        SharedMembershipState::reset();
        $this->state = SharedMembershipState::getInstance();
    }

    #[Given('the :activityType activity template is loaded')]
    public function theActivityTemplateIsLoaded(string $activityType): void
    {
        $this->activityType = $activityType;

        $kernel = new Kernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->state->engine = $container->get(MembershipEngine::class);
        $this->state->eventLogger = $container->get(CaseEventLogger::class);
        $this->state->caseRepository = $container->get(CaseRepository::class);
        $this->state->pointsAccountRepository = $container->get(PointsAccountRepository::class);
    }

    #[Given('a member :memberId with a purchase of :amount PLN at store :storeId')]
    public function aMemberWithAPurchase(string $memberId, int $amount, string $storeId): void
    {
        $this->state->activityData = [
            'member_id' => $memberId,
            'transaction_id' => 'TXN-' . uniqid(),
            'amount' => $amount,
            'currency' => 'PLN',
            'store_id' => $storeId,
        ];
    }

    #[Given('a member :memberId with an online purchase of :amount PLN')]
    public function aMemberWithAnOnlinePurchase(string $memberId, int $amount): void
    {
        $this->state->activityData = [
            'member_id' => $memberId,
            'transaction_id' => 'TXN-ONLINE-' . uniqid(),
            'order_id' => 'ORD-' . uniqid(),
            'amount' => $amount,
            'currency' => 'PLN',
        ];
    }

    #[Given('a referral from member :referrerId for new member :referredId with code :code')]
    public function aReferral(string $referrerId, string $referredId, string $code): void
    {
        $this->state->activityData = [
            'referrer_member_id' => $referrerId,
            'referred_member_id' => $referredId,
            'referral_code' => $code,
        ];
    }

    #[Given('the member has :totalPoints total points on :tier tier')]
    public function theMemberHasTotalPoints(int $totalPoints, string $tier): void
    {
        $this->state->activityData['total_points'] = $totalPoints;
        $this->state->activityData['current_tier'] = $tier;
    }

    #[Given('a member :memberId with :balance points balance')]
    public function aMemberWithPointsBalance(string $memberId, int $balance): void
    {
        $this->state->activityData['member_id'] = $memberId;

        $account = new PointsAccount($memberId, $balance);
        $this->state->pointsAccountRepository->save($account);
    }

    #[Given('the member wants to redeem reward :rewardId costing :cost points')]
    public function theMemberWantsToRedeemReward(string $rewardId, int $cost): void
    {
        $this->state->activityData['reward_id'] = $rewardId;
        $this->state->activityData['reward_points_cost'] = $cost;
    }

    #[Then('the member :memberId should have :balance points remaining')]
    public function theMemberShouldHavePointsRemaining(string $memberId, int $balance): void
    {
        $account = $this->state->pointsAccountRepository->findByMemberId($memberId);
        assert($account !== null, "No points account found for member '{$memberId}'");
        assert(
            $account->balance() === $balance,
            "Member '{$memberId}' has {$account->balance()} points, expected {$balance}",
        );
    }

    #[When('the loyalty activity is started')]
    public function theLoyaltyActivityIsStarted(): void
    {
        $this->state->currentCase = $this->state->engine->startCase(
            $this->activityType,
            $this->state->activityData,
        );

        $this->parseEventLog();
    }

    #[Then('the case should be completed with outcome :outcome')]
    public function theCaseShouldBeCompletedWithOutcome(string $outcome): void
    {
        $case = $this->state->currentCase;
        assert($case !== null, 'No case started');
        assert($case->status()->value === 'completed', "Case status is '{$case->status()->value}', expected 'completed'");
        assert($case->caseOutcome()?->value === $outcome, "Case outcome is '{$case->caseOutcome()?->value}', expected '{$outcome}'");
    }

    #[Then('the following steps should have been executed in order:')]
    public function theFollowingStepsShouldHaveBeenExecutedInOrder(TableNode $table): void
    {
        $expected = array_column($table->getHash(), 'step');
        $actual = $this->state->initializedSteps;

        assert($actual === $expected, sprintf(
            "Steps order mismatch.\nExpected: %s\nActual:   %s",
            implode(' → ', $expected),
            implode(' → ', $actual),
        ));
    }

    #[Then('the step :stepId should have been executed')]
    public function theStepShouldHaveBeenExecuted(string $stepId): void
    {
        assert(
            in_array($stepId, $this->state->initializedSteps, true),
            "Step '{$stepId}' was not executed. Executed steps: " . implode(', ', $this->state->initializedSteps),
        );
    }

    #[Then('the step :stepId should not have been executed')]
    public function theStepShouldNotHaveBeenExecuted(string $stepId): void
    {
        assert(
            !in_array($stepId, $this->state->initializedSteps, true),
            "Step '{$stepId}' was executed but should not have been",
        );
    }

    private function parseEventLog(): void
    {
        $this->state->initializedSteps = [];
        $this->state->stepOutcomes = [];

        foreach ($this->state->eventLogger->getLog() as $line) {
            if (str_contains($line, '[ACTION INIT]') && preg_match('/step=(\S+)/', $line, $m)) {
                $this->state->initializedSteps[] = $m[1];
            }
            if (str_contains($line, '[ACTION DONE]') && preg_match('/step=(\S+)\s+outcome=(\S+)/', $line, $m)) {
                $this->state->stepOutcomes[$m[1]] = $m[2];
            }
        }
    }
}
