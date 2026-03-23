<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Event\ActionFinished;
use App\Onboarding\Event\ActionInitialized;
use App\Onboarding\Event\ActionPending;
use App\Onboarding\Event\CaseFinished;
use App\Onboarding\Event\CaseStarted;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\StepId;
use App\Onboarding\Template\OnboardingTemplate;
use App\Onboarding\Template\StageDefinition;
use App\Onboarding\Template\StepDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OnboardingCaseTest extends TestCase
{
    /**
     * Minimal 2-step template: collect → verify → terminal
     */
    private function twoStepTemplate(): OnboardingTemplate
    {
        return new OnboardingTemplate(
            name: 'Test Template',
            clientType: 'test',
            version: 1,
            description: 'Test',
            stages: [
                new StageDefinition('intake', 'Intake', [
                    new StepDefinition(
                        id: 'collect_data',
                        name: 'Collect Data',
                        service: 'form',
                        action: 'collect',
                        outcomes: [
                            'completed' => ['next_step' => 'verify'],
                            'abandoned' => ['terminal' => true, 'case_outcome' => 'abandoned'],
                        ],
                    ),
                ]),
                new StageDefinition('verification', 'Verification', [
                    new StepDefinition(
                        id: 'verify',
                        name: 'Verify',
                        service: 'verifier',
                        action: 'check',
                        outcomes: [
                            'passed' => ['terminal' => true, 'case_outcome' => 'approved'],
                            'failed' => ['terminal' => true, 'case_outcome' => 'rejected'],
                        ],
                    ),
                ]),
            ],
        );
    }

    /**
     * Template with branching: risk → low_risk or high_risk paths
     */
    private function branchingTemplate(): OnboardingTemplate
    {
        return new OnboardingTemplate(
            name: 'Branching Template',
            clientType: 'branching',
            version: 1,
            description: 'Test branching',
            stages: [
                new StageDefinition('risk', 'Risk Assessment', [
                    new StepDefinition(
                        id: 'calculate_risk',
                        name: 'Calculate Risk',
                        service: 'risk',
                        action: 'calculate',
                        outcomes: [
                            'low_risk' => ['next_step' => 'basic_docs'],
                            'high_risk' => ['terminal' => true, 'case_outcome' => 'rejected'],
                        ],
                    ),
                ]),
                new StageDefinition('docs', 'Documents', [
                    new StepDefinition(
                        id: 'basic_docs',
                        name: 'Basic Documents',
                        service: 'documents',
                        action: 'request',
                        outcomes: [
                            'all_received' => ['terminal' => true, 'case_outcome' => 'approved'],
                            'expired' => ['terminal' => true, 'case_outcome' => 'expired'],
                        ],
                    ),
                ]),
            ],
        );
    }

    // --- Start ---

    #[Test]
    public function starting_case_initializes_first_step_and_emits_events(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), ['name' => 'Acme']);

        self::assertSame(Status::Pending, $case->status());
        self::assertNotNull($case->currentStepId());
        self::assertSame('collect_data', $case->currentStepId()->value);
        self::assertSame('intake', $case->currentStageId()->value);
        self::assertNull($case->caseOutcome());

        $events = $case->releaseEvents();

        // CaseStarted + ActionInitialized + ActionPending
        self::assertCount(3, $events);
        self::assertInstanceOf(CaseStarted::class, $events[0]);
        self::assertInstanceOf(ActionInitialized::class, $events[1]);
        self::assertInstanceOf(ActionPending::class, $events[2]);

        self::assertSame('Test Template', $events[0]->templateName);
        self::assertSame('collect_data', $events[1]->stepId);
        self::assertSame('form', $events[1]->service);
        self::assertSame('collect', $events[1]->action);
    }

    #[Test]
    public function release_events_clears_recorded_events(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);

        $first = $case->releaseEvents();
        $second = $case->releaseEvents();

        self::assertNotEmpty($first);
        self::assertEmpty($second);
    }

    // --- Step outcomes ---

    #[Test]
    public function handling_outcome_advances_to_next_step(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents(); // clear start events

        $case->handleStepOutcome(new StepId('collect_data'), new Outcome('completed'));

        self::assertSame('verify', $case->currentStepId()->value);
        self::assertSame('verification', $case->currentStageId()->value);
        self::assertSame(Status::Pending, $case->status());

        $events = $case->releaseEvents();
        // ActionFinished(collect_data) + ActionInitialized(verify) + ActionPending(verify)
        self::assertCount(3, $events);
        self::assertInstanceOf(ActionFinished::class, $events[0]);
        self::assertSame('collect_data', $events[0]->stepId);
        self::assertSame('completed', $events[0]->outcome);
        self::assertFalse($events[0]->isTerminal);
        self::assertSame('verify', $events[0]->nextStepId);
    }

    #[Test]
    public function terminal_outcome_finishes_case(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('collect_data'), new Outcome('completed'));
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('verify'), new Outcome('passed'));

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());

        $events = $case->releaseEvents();
        // ActionFinished(verify, terminal) + CaseFinished
        self::assertCount(2, $events);

        $actionFinished = $events[0];
        self::assertInstanceOf(ActionFinished::class, $actionFinished);
        self::assertTrue($actionFinished->isTerminal);
        self::assertSame('approved', $actionFinished->caseOutcome);

        self::assertInstanceOf(CaseFinished::class, $events[1]);
        self::assertSame('approved', $events[1]->outcome);
    }

    #[Test]
    public function rejection_at_first_step_finishes_case(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('collect_data'), new Outcome('abandoned'));

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Abandoned, $case->caseOutcome());
    }

    // --- Branching ---

    #[Test]
    public function low_risk_branch_continues_to_documents(): void
    {
        $case = OnboardingCase::start($this->branchingTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('calculate_risk'), new Outcome('low_risk'));

        self::assertSame('basic_docs', $case->currentStepId()->value);
        self::assertSame('docs', $case->currentStageId()->value);

        $case->releaseEvents();
        $case->handleStepOutcome(new StepId('basic_docs'), new Outcome('all_received'));

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Approved, $case->caseOutcome());
    }

    #[Test]
    public function high_risk_branch_rejects_immediately(): void
    {
        $case = OnboardingCase::start($this->branchingTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('calculate_risk'), new Outcome('high_risk'));

        self::assertSame(Status::Completed, $case->status());
        self::assertSame(CaseOutcome::Rejected, $case->caseOutcome());
    }

    #[Test]
    public function expired_documents_finish_with_expired_outcome(): void
    {
        $case = OnboardingCase::start($this->branchingTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('calculate_risk'), new Outcome('low_risk'));
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('basic_docs'), new Outcome('expired'));

        self::assertSame(CaseOutcome::Expired, $case->caseOutcome());
    }

    // --- Error cases ---

    #[Test]
    public function unknown_step_throws(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step nonexistent not found');

        $case->handleStepOutcome(new StepId('nonexistent'), new Outcome('done'));
    }

    #[Test]
    public function unknown_outcome_throws(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No transition defined for outcome 'unknown_result'");

        $case->handleStepOutcome(new StepId('collect_data'), new Outcome('unknown_result'));
    }

    // --- Stages stream ---

    #[Test]
    public function stages_returns_stream(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);

        $names = [];
        $case->stages()->forEach(function ($stage) use (&$names) {
            $names[] = $stage->name;
        });

        self::assertSame(['Intake', 'Verification'], $names);
    }

    // --- Metadata preservation ---

    #[Test]
    public function outcome_metadata_is_preserved_in_events(): void
    {
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(
            new StepId('collect_data'),
            new Outcome('completed', ['source' => 'web_form', 'ip' => '1.2.3.4']),
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
        $case = OnboardingCase::start($this->twoStepTemplate(), []);
        $case->releaseEvents();

        $case->handleStepOutcome(new StepId('collect_data'), new Outcome('completed'));

        $statuses = [];
        $case->stages()->forEach(function ($stage) use (&$statuses) {
            $statuses[$stage->name] = $stage->status();
        });

        self::assertSame(Status::Completed, $statuses['Intake']);
        self::assertSame(Status::Pending, $statuses['Verification']);
    }
}
