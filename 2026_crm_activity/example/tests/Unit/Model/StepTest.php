<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\State\IllegalStateTransitionException;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\Step;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\StepFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepTest extends TestCase
{
    // --- given ---

    private function givenInitializedStep(): Step
    {
        return StepFactory::initialized();
    }

    private function givenPendingStep(): Step
    {
        return StepFactory::pending();
    }

    private function givenCompletedStep(): Step
    {
        return StepFactory::completed(outcomeValue: 'clean');
    }

    // --- when ---

    private function whenTheStepIsMarkedPending(Step $step): void
    {
        $step->markPending();
    }

    private function whenTheStepIsCompleted(Step $step, string $outcomeValue = 'clean', array $metadata = []): void
    {
        $step->complete(OutcomeFactory::withValue($outcomeValue, $metadata));
    }

    private function whenTheStepFails(Step $step, string $outcomeValue = 'timeout'): void
    {
        $step->fail(OutcomeFactory::withValue($outcomeValue));
    }

    // --- then ---

    private function thenStepHasStatus(Step $step, Status $expected): void
    {
        self::assertSame($expected, $step->status());
    }

    private function thenStepHasOutcome(Step $step, string $expectedValue): void
    {
        self::assertNotNull($step->outcome());
        self::assertSame($expectedValue, $step->outcome()->value);
    }

    // --- tests ---

    #[Test]
    public function new_step_is_initialized(): void
    {
        $step = $this->givenInitializedStep();

        $this->thenStepHasStatus($step, Status::Initialized);
        self::assertNull($step->outcome());
        self::assertNull($step->startedAt());
        self::assertNull($step->completedAt());
    }

    #[Test]
    public function step_exposes_identity_and_service_action(): void
    {
        $step = StepFactory::initialized(id: 'verify_nip', service: 'kuc', action: 'check_registry');

        self::assertSame('verify_nip', $step->stepId->value);
        self::assertSame('kuc', $step->serviceAction->service);
        self::assertSame('check_registry', $step->serviceAction->action);
        self::assertSame('kuc.check_registry', $step->serviceAction->toString());
    }

    #[Test]
    public function mark_pending_transitions_step(): void
    {
        $step = $this->givenInitializedStep();

        $this->whenTheStepIsMarkedPending($step);

        $this->thenStepHasStatus($step, Status::Pending);
        self::assertNotNull($step->startedAt());
    }

    #[Test]
    public function complete_with_outcome(): void
    {
        $step = $this->givenPendingStep();

        $this->whenTheStepIsCompleted($step, 'clean', ['nip' => '5261234567']);

        $this->thenStepHasStatus($step, Status::Completed);
        $this->thenStepHasOutcome($step, 'clean');
        self::assertSame('5261234567', $step->outcome()->metadata['nip']);
        self::assertNotNull($step->completedAt());
    }

    #[Test]
    public function fail_with_outcome(): void
    {
        $step = $this->givenPendingStep();

        $this->whenTheStepFails($step, 'timeout');

        $this->thenStepHasStatus($step, Status::Failed);
        $this->thenStepHasOutcome($step, 'timeout');
    }

    #[Test]
    public function cannot_complete_without_pending_first(): void
    {
        $step = $this->givenInitializedStep();

        $this->expectException(IllegalStateTransitionException::class);
        $this->whenTheStepIsCompleted($step);
    }

    #[Test]
    public function cannot_fail_without_pending_first(): void
    {
        $step = $this->givenInitializedStep();

        $this->expectException(IllegalStateTransitionException::class);
        $this->whenTheStepFails($step);
    }

    #[Test]
    public function cannot_mark_pending_twice(): void
    {
        $step = $this->givenPendingStep();

        $this->expectException(IllegalStateTransitionException::class);
        $this->whenTheStepIsMarkedPending($step);
    }

    #[Test]
    public function cannot_complete_after_completed(): void
    {
        $step = $this->givenCompletedStep();

        $this->expectException(IllegalStateTransitionException::class);
        $this->whenTheStepIsCompleted($step, 'flagged');
    }
}
