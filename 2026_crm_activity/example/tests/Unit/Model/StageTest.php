<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\Stage;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\Step;
use App\Onboarding\Model\StepId;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\StageBuilder;
use Tests\Factory\StepFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    // --- given ---

    private function givenEmptyStage(): Stage
    {
        return StageBuilder::aStage()->build();
    }

    private function givenStageWithSteps(Step ...$steps): Stage
    {
        return StageBuilder::aStage()->withSteps(...$steps)->build();
    }

    private function givenStageWithStartedStep(string $stepId): Stage
    {
        return StageBuilder::aStage()
            ->withStep(StepFactory::initialized($stepId))
            ->withStartedStep($stepId)
            ->build();
    }

    // --- when ---

    private function whenStepIsStarted(Stage $stage, string $stepId): Step
    {
        return $stage->startStep(new StepId($stepId));
    }

    private function whenStageIsMarkedCompleted(Stage $stage): void
    {
        $stage->markCompleted();
    }

    // --- then ---

    private function thenStageHasStatus(Stage $stage, Status $expected): void
    {
        self::assertSame($expected, $stage->status());
    }

    private function thenStageHasStep(Stage $stage, string $stepId): void
    {
        self::assertTrue($stage->hasStep(new StepId($stepId)));
    }

    private function thenStageDoesNotHaveStep(Stage $stage, string $stepId): void
    {
        self::assertFalse($stage->hasStep(new StepId($stepId)));
    }

    private function thenCurrentStepIs(Stage $stage, string $expectedStepId): void
    {
        self::assertNotNull($stage->currentStep());
        self::assertSame($expectedStepId, $stage->currentStep()->stepId->value);
    }

    // --- tests ---

    #[Test]
    public function new_stage_is_initialized(): void
    {
        $stage = $this->givenEmptyStage();

        $this->thenStageHasStatus($stage, Status::Initialized);
        self::assertSame('verification', $stage->stageId->value);
        self::assertSame('Client Verification', $stage->name);
        self::assertNull($stage->currentStep());
    }

    #[Test]
    public function can_add_and_retrieve_steps(): void
    {
        $stage = $this->givenStageWithSteps(
            StepFactory::initialized('check_kuc'),
            StepFactory::initialized('calculate_risk', service: 'risk', action: 'calculate'),
        );

        $this->thenStageHasStep($stage, 'check_kuc');
        $this->thenStageHasStep($stage, 'calculate_risk');
        $this->thenStageDoesNotHaveStep($stage, 'nonexistent');
    }

    #[Test]
    public function get_step_returns_option_some_for_existing(): void
    {
        $stage = $this->givenStageWithSteps(StepFactory::initialized('check_kuc'));

        $result = $stage->getStep(new StepId('check_kuc'));

        self::assertTrue($result->isPresent());
        self::assertSame('check_kuc', $result->get()->stepId->value);
    }

    #[Test]
    public function get_step_returns_option_none_for_missing(): void
    {
        $stage = $this->givenEmptyStage();

        $result = $stage->getStep(new StepId('nonexistent'));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function start_step_marks_step_pending_and_stage_pending(): void
    {
        $stage = $this->givenStageWithSteps(StepFactory::initialized('check_kuc'));

        $step = $this->whenStepIsStarted($stage, 'check_kuc');

        $this->thenStepHasStatus($step, Status::Pending);
        $this->thenStageHasStatus($stage, Status::Pending);
        $this->thenCurrentStepIs($stage, 'check_kuc');
    }

    #[Test]
    public function start_step_throws_for_unknown_step(): void
    {
        $stage = $this->givenEmptyStage();

        $this->expectException(\InvalidArgumentException::class);
        $this->whenStepIsStarted($stage, 'unknown');
    }

    #[Test]
    public function starting_second_step_keeps_stage_pending(): void
    {
        $stage = $this->givenStageWithSteps(
            StepFactory::initialized('check_kuc'),
            StepFactory::initialized('calculate_risk', service: 'risk', action: 'calculate'),
        );

        $this->whenStepIsStarted($stage, 'check_kuc');
        $stage->getStep(new StepId('check_kuc'))->get()->complete(OutcomeFactory::clean());
        $this->whenStepIsStarted($stage, 'calculate_risk');

        $this->thenStageHasStatus($stage, Status::Pending);
        $this->thenCurrentStepIs($stage, 'calculate_risk');
    }

    #[Test]
    public function mark_completed_changes_status(): void
    {
        $stage = $this->givenStageWithStartedStep('check_kuc');

        $this->whenStageIsMarkedCompleted($stage);

        $this->thenStageHasStatus($stage, Status::Completed);
    }

    #[Test]
    public function steps_returns_stream_of_all_steps(): void
    {
        $stage = $this->givenStageWithSteps(
            StepFactory::initialized('step_a'),
            StepFactory::initialized('step_b'),
            StepFactory::initialized('step_c'),
        );

        $ids = $stage->steps()->map(fn(Step $s) => $s->stepId->value)->toArray();

        self::assertCount(3, $ids);
        self::assertContains('step_a', $ids);
        self::assertContains('step_b', $ids);
        self::assertContains('step_c', $ids);
    }

    // --- private helper for step status (shared) ---

    private function thenStepHasStatus(Step $step, Status $expected): void
    {
        self::assertSame($expected, $step->status());
    }
}
