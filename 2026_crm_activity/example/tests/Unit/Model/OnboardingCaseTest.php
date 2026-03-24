<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Event\ActionFinished;
use App\Onboarding\Event\ActionInitialized;
use App\Onboarding\Event\ActionPending;
use App\Onboarding\Event\CaseEvent;
use App\Onboarding\Event\CaseFinished;
use App\Onboarding\Event\CaseStarted;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\StepId;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\TemplateBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OnboardingCaseTest extends TestCase
{
    // --- given ---

    private function givenStartedTwoStepCase(array $clientData = []): OnboardingCase
    {
        return OnboardingCase::start(TemplateBuilder::twoStepTemplate(), $clientData);
    }

    private function givenStartedBranchingCase(): OnboardingCase
    {
        return OnboardingCase::start(TemplateBuilder::branchingTemplate(), []);
    }

    private function givenCaseAfterCollectData(): OnboardingCase
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();
        $this->whenTheCaseHandlesOutcome($case, 'collect_data', OutcomeFactory::completed());

        return $case;
    }

    private function givenCaseAtRiskStep(): OnboardingCase
    {
        $case = $this->givenStartedBranchingCase();
        $case->releaseEvents();

        return $case;
    }

    private function givenCaseAtDocumentsStep(): OnboardingCase
    {
        $case = $this->givenCaseAtRiskStep();
        $this->whenTheCaseHandlesOutcome($case, 'calculate_risk', OutcomeFactory::lowRisk());
        $case->releaseEvents();

        return $case;
    }

    // --- when ---

    private function whenTheCaseHandlesOutcome(OnboardingCase $case, string $stepId, \App\Onboarding\Model\Outcome $outcome): void
    {
        $case->handleStepOutcome(new StepId($stepId), $outcome);
    }

    // --- then ---

    private function thenCaseStatusIs(OnboardingCase $case, Status $expected): void
    {
        self::assertSame($expected, $case->status());
    }

    private function thenCaseOutcomeIs(OnboardingCase $case, CaseOutcome $expected): void
    {
        self::assertSame($expected, $case->caseOutcome());
    }

    private function thenCurrentStepIs(OnboardingCase $case, string $expectedStepId): void
    {
        self::assertNotNull($case->currentStepId());
        self::assertSame($expectedStepId, $case->currentStepId()->value);
    }

    private function thenCurrentStageIs(OnboardingCase $case, string $expectedStageId): void
    {
        self::assertNotNull($case->currentStageId());
        self::assertSame($expectedStageId, $case->currentStageId()->value);
    }

    /**
     * @return CaseEvent[]
     */
    private function thenEventsContain(OnboardingCase $case, string ...$eventClasses): array
    {
        $events = $case->releaseEvents();
        $types = array_map(fn(CaseEvent $e) => $e::class, $events);

        foreach ($eventClasses as $class) {
            self::assertContains($class, $types, "Expected event {$class} not found in: " . implode(', ', $types));
        }

        return $events;
    }

    private function thenThereAreNoMoreEvents(OnboardingCase $case): void
    {
        self::assertEmpty($case->releaseEvents());
    }

    /**
     * @return array<string, Status>
     */
    private function thenStageStatusesAre(OnboardingCase $case): array
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
        $case = $this->givenStartedTwoStepCase(['name' => 'Acme']);

        $this->thenCaseStatusIs($case, Status::Pending);
        $this->thenCurrentStepIs($case, 'collect_data');
        $this->thenCurrentStageIs($case, 'intake');
        self::assertNull($case->caseOutcome());

        $events = $this->thenEventsContain($case, CaseStarted::class, ActionInitialized::class, ActionPending::class);

        self::assertCount(3, $events);
        self::assertSame('Test Template', $events[0]->templateName);
        self::assertSame('collect_data', $events[1]->stepId);
        self::assertSame('form', $events[1]->service);
        self::assertSame('collect', $events[1]->action);
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

        $this->whenTheCaseHandlesOutcome($case, 'collect_data', OutcomeFactory::completed());

        $this->thenCurrentStepIs($case, 'verify');
        $this->thenCurrentStageIs($case, 'verification');
        $this->thenCaseStatusIs($case, Status::Pending);

        $events = $case->releaseEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(ActionFinished::class, $events[0]);
        self::assertSame('collect_data', $events[0]->stepId);
        self::assertFalse($events[0]->isTerminal);
        self::assertSame('verify', $events[0]->nextStepId);
    }

    #[Test]
    public function terminal_outcome_finishes_case(): void
    {
        $case = $this->givenCaseAfterCollectData();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'verify', OutcomeFactory::passed());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::Approved);

        $events = $case->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ActionFinished::class, $events[0]);
        self::assertTrue($events[0]->isTerminal);
        self::assertSame('approved', $events[0]->caseOutcome);
        self::assertInstanceOf(CaseFinished::class, $events[1]);
    }

    #[Test]
    public function rejection_at_first_step_finishes_case(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'collect_data', OutcomeFactory::withValue('abandoned'));

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::Abandoned);
    }

    // --- Branching ---

    #[Test]
    public function low_risk_branch_continues_to_documents(): void
    {
        $case = $this->givenCaseAtRiskStep();

        $this->whenTheCaseHandlesOutcome($case, 'calculate_risk', OutcomeFactory::lowRisk());

        $this->thenCurrentStepIs($case, 'basic_docs');
        $this->thenCurrentStageIs($case, 'docs');

        $case->releaseEvents();
        $this->whenTheCaseHandlesOutcome($case, 'basic_docs', OutcomeFactory::withValue('all_received'));

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::Approved);
    }

    #[Test]
    public function high_risk_branch_rejects_immediately(): void
    {
        $case = $this->givenCaseAtRiskStep();

        $this->whenTheCaseHandlesOutcome($case, 'calculate_risk', OutcomeFactory::highRisk());

        $this->thenCaseStatusIs($case, Status::Completed);
        $this->thenCaseOutcomeIs($case, CaseOutcome::Rejected);
    }

    #[Test]
    public function expired_documents_finish_with_expired_outcome(): void
    {
        $case = $this->givenCaseAtDocumentsStep();

        $this->whenTheCaseHandlesOutcome($case, 'basic_docs', OutcomeFactory::expired());

        $this->thenCaseOutcomeIs($case, CaseOutcome::Expired);
    }

    // --- Error cases ---

    #[Test]
    public function unknown_step_throws(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step nonexistent not found');

        $this->whenTheCaseHandlesOutcome($case, 'nonexistent', OutcomeFactory::approved());
    }

    #[Test]
    public function unknown_outcome_throws(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No transition defined for outcome 'unknown_result'");

        $this->whenTheCaseHandlesOutcome($case, 'collect_data', OutcomeFactory::withValue('unknown_result'));
    }

    // --- Stages stream ---

    #[Test]
    public function stages_returns_stream(): void
    {
        $case = $this->givenStartedTwoStepCase();

        $names = $case->stages()->map(fn($stage) => $stage->name)->toArray();

        self::assertSame(['Intake', 'Verification'], $names);
    }

    // --- Metadata preservation ---

    #[Test]
    public function outcome_metadata_is_preserved_in_events(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome(
            $case,
            'collect_data',
            OutcomeFactory::completed(['source' => 'web_form', 'ip' => '1.2.3.4']),
        );

        $events = $case->releaseEvents();
        $finished = $events[0];
        self::assertInstanceOf(ActionFinished::class, $finished);
        self::assertSame('web_form', $finished->metadata['source']);
        self::assertSame('1.2.3.4', $finished->metadata['ip']);
    }

    // --- Stage completion on transition ---

    #[Test]
    public function previous_stage_is_completed_when_moving_to_next(): void
    {
        $case = $this->givenStartedTwoStepCase();
        $case->releaseEvents();

        $this->whenTheCaseHandlesOutcome($case, 'collect_data', OutcomeFactory::completed());

        $statuses = $this->thenStageStatusesAre($case);

        self::assertSame(Status::Completed, $statuses['Intake']);
        self::assertSame(Status::Pending, $statuses['Verification']);
    }
}
