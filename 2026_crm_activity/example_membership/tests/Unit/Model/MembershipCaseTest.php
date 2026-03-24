<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Event\ActionFinished;
use App\Membership\Event\ActionInitialized;
use App\Membership\Event\ActionPending;
use App\Membership\Event\CaseEvent;
use App\Membership\Event\CaseFinished;
use App\Membership\Event\CaseStarted;
use App\Membership\Model\CaseOutcome;
use App\Membership\Model\MembershipCase;
use App\Membership\Model\Status;
use App\Membership\Model\StepId;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\TemplateBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MembershipCaseTest extends TestCase
{
    // --- given ---

    private function givenStartedTwoStepCase(array $activityData = []): MembershipCase
    {
        return MembershipCase::start(TemplateBuilder::twoStepTemplate(), $activityData);
    }

    private function givenStartedBranchingCase(): MembershipCase
    {
        return MembershipCase::start(TemplateBuilder::branchingTemplate(), []);
    }

    private function givenCaseAfterValidation(): MembershipCase
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();
        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::valid());

        return $case;
    }

    private function givenCaseAtTierStep(): MembershipCase
    {
        $case = $this->givenStartedBranchingCase();
        $case->releaseEvents();
        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::valid());
        $case->releaseEvents();
        $this->whenTheCaseHandlesOutcome($case, 'calculate_points', OutcomeFactory::pointsCalculated());
        $case->releaseEvents();

        return $case;
    }

    // --- when ---

    private function whenTheCaseHandlesOutcome(MembershipCase $case, string $stepId, \App\Membership\Model\Outcome $outcome): void
    {
        $case->handleStepOutcome(new StepId($stepId), $outcome);
    }

    // --- then ---

    private function thenCaseStatusIs(MembershipCase $case, Status $expected): void
    {
        self::assertSame($expected, $case->status());
    }

    private function thenCaseOutcomeIs(MembershipCase $case, CaseOutcome $expected): void
    {
        self::assertSame($expected, $case->caseOutcome());
    }

    private function thenCurrentStepIs(MembershipCase $case, string $expectedStepId): void
    {
        self::assertNotNull($case->currentStepId());
        self::assertSame($expectedStepId, $case->currentStepId()->value);
    }

    private function thenCurrentStageIs(MembershipCase $case, string $expectedStageId): void
    {
        self::assertNotNull($case->currentStageId());
        self::assertSame($expectedStageId, $case->currentStageId()->value);
    }

    /**
     * @return CaseEvent[]
     */
    private function thenEventsContain(MembershipCase $case, string ...$eventClasses): array
    {
        $events = $case->releaseEvents();
        $types = array_map(fn(CaseEvent $e) => $e::class, $events);

        foreach ($eventClasses as $class) {
            self::assertContains($class, $types, "Expected event {$class} not found in: " . implode(', ', $types));
        }

        return $events;
    }

    private function thenThereAreNoMoreEvents(MembershipCase $case): void
    {
        self::assertEmpty($case->releaseEvents());
    }

    /**
     * @return array<string, Status>
     */
    private function thenStageStatusesAre(MembershipCase $case): array
    {
        $statuses = [];
        $case->stages()->forEach(function ($stage) use (&$statuses) {
            $statuses[$stage->name] = $stage->status();
        });

        return $statuses;
    }

    // --- Start ---

    #[Test]
    public function starting_case_initializes_first_step_and_emits_events(): void
    {
        $case = $this->givenStartedTwoStepCase(['member_id' => 'MBR-001', 'amount' => 100]);

        $this->thenCaseStatusIs($case, Status::Pending);
        $this->thenCurrentStepIs($case, 'validate_transaction');
        $this->thenCurrentStageIs($case, 'validation');
        self::assertNull($case->caseOutcome());

        $events = $this->thenEventsContain($case, CaseStarted::class, ActionInitialized::class, ActionPending::class);

        self::assertCount(3, $events);
        self::assertSame('Test Template', $events[0]->templateName);
        self::assertSame('validate_transaction', $events[1]->stepId);
        self::assertSame('transaction', $events[1]->service);
        self::assertSame('validate', $events[1]->action);
    }

    #[Test]
    public function release_events_clears_recorded_events(): void
    {
        $case = $this->givenStartedTwoStepCase();

        $first = $case->releaseEvents();
        self::assertNotEmpty($first);

        $this->thenThereAreNoMoreEvents($case);
    }

    // --- Step outcomes ---

    #[Test]
    public function handling_outcome_advances_to_next_step(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::valid());

        $this->thenCurrentStepIs($case, 'calculate_points');
        $this->thenCurrentStageIs($case, 'points');
        $this->thenCaseStatusIs($case, Status::Pending);

        $events = $case->releaseEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(ActionFinished::class, $events[0]);
        self::assertSame('validate_transaction', $events[0]->stepId);
        self::assertFalse($events[0]->isTerminal);
        self::assertSame('calculate_points', $events[0]->nextStepId);
    }

    #[Test]
    public function terminal_outcome_finishes_case_with_points_awarded(): void
    {
        $case = $this->givenCaseAfterValidation();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'calculate_points', OutcomeFactory::pointsCalculated());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::PointsAwarded);

        $events = $case->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ActionFinished::class, $events[0]);
        self::assertTrue($events[0]->isTerminal);
        self::assertSame('points_awarded', $events[0]->caseOutcome);
        self::assertInstanceOf(CaseFinished::class, $events[1]);
    }

    #[Test]
    public function invalid_transaction_rejects_case(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::invalid());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::Rejected);
    }

    #[Test]
    public function zero_points_finishes_with_completed_no_points(): void
    {
        $case = $this->givenCaseAfterValidation();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'calculate_points', OutcomeFactory::zeroPoints());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::CompletedNoPoints);
    }

    // --- Branching ---

    #[Test]
    public function tier_unchanged_awards_points(): void
    {
        $case = $this->givenCaseAtTierStep();

        $this->whenTheCaseHandlesOutcome($case, 'evaluate_tier', OutcomeFactory::tierUnchanged());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::PointsAwarded);
    }

    #[Test]
    public function tier_upgrade_results_in_tier_upgraded_outcome(): void
    {
        $case = $this->givenCaseAtTierStep();

        $this->whenTheCaseHandlesOutcome($case, 'evaluate_tier', OutcomeFactory::tierUpgrade());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::TierUpgraded);
    }

    // --- Error cases ---

    #[Test]
    public function unknown_step_throws(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step nonexistent not found');

        $this->whenTheCaseHandlesOutcome($case, 'nonexistent', OutcomeFactory::valid());
    }

    #[Test]
    public function unknown_outcome_throws(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No transition defined for outcome 'unknown_result'");

        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::withValue('unknown_result'));
    }

    // --- Stages stream ---

    #[Test]
    public function stages_returns_stream(): void
    {
        $case = $this->givenStartedTwoStepCase();

        $names = $case->stages()->map(fn($stage) => $stage->name)->toArray();

        self::assertSame(['Validation', 'Points Processing'], $names);
    }

    // --- Metadata preservation ---

    #[Test]
    public function outcome_metadata_is_preserved_in_events(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome(
            $case,
            'validate_transaction',
            OutcomeFactory::valid(['transaction_id' => 'TXN-123', 'store_id' => 'STORE-01']),
        );

        $events = $case->releaseEvents();
        $finished = $events[0];
        self::assertInstanceOf(ActionFinished::class, $finished);
        self::assertSame('TXN-123', $finished->metadata['transaction_id']);
        self::assertSame('STORE-01', $finished->metadata['store_id']);
    }

    // --- Stage completion on transition ---

    #[Test]
    public function previous_stage_is_completed_when_moving_to_next(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'validate_transaction', OutcomeFactory::valid());

        $statuses = $this->thenStageStatusesAre($case);

        self::assertSame(Status::Completed, $statuses['Validation']);
        self::assertSame(Status::Pending, $statuses['Points Processing']);
    }
}
