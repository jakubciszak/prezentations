<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\ServiceAction;
use App\Onboarding\Model\Stage;
use App\Onboarding\Model\StageId;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\Step;
use App\Onboarding\Model\StepId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    private function createStage(): Stage
    {
        return new Stage(new StageId('verification'), 'Client Verification');
    }

    private function createStep(string $id, string $service = 'kuc', string $action = 'check'): Step
    {
        return new Step(
            new StepId($id),
            "Step {$id}",
            new ServiceAction($service, $action),
        );
    }

    #[Test]
    public function new_stage_is_initialized(): void
    {
        $stage = $this->createStage();

        self::assertSame(Status::Initialized, $stage->status());
        self::assertSame('verification', $stage->stageId->value);
        self::assertSame('Client Verification', $stage->name);
        self::assertNull($stage->currentStep());
    }

    #[Test]
    public function can_add_and_retrieve_steps(): void
    {
        $stage = $this->createStage();
        $step1 = $this->createStep('check_kuc');
        $step2 = $this->createStep('calculate_risk', 'risk', 'calculate');

        $stage->addStep($step1);
        $stage->addStep($step2);

        self::assertTrue($stage->hasStep(new StepId('check_kuc')));
        self::assertTrue($stage->hasStep(new StepId('calculate_risk')));
        self::assertFalse($stage->hasStep(new StepId('nonexistent')));
    }

    #[Test]
    public function get_step_returns_option_some_for_existing(): void
    {
        $stage = $this->createStage();
        $stage->addStep($this->createStep('check_kuc'));

        $result = $stage->getStep(new StepId('check_kuc'));

        self::assertTrue($result->isPresent());
        self::assertSame('check_kuc', $result->get()->stepId->value);
    }

    #[Test]
    public function get_step_returns_option_none_for_missing(): void
    {
        $stage = $this->createStage();

        $result = $stage->getStep(new StepId('nonexistent'));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function start_step_marks_step_pending_and_stage_pending(): void
    {
        $stage = $this->createStage();
        $stage->addStep($this->createStep('check_kuc'));

        $step = $stage->startStep(new StepId('check_kuc'));

        self::assertSame(Status::Pending, $step->status());
        self::assertSame(Status::Pending, $stage->status());
        self::assertNotNull($stage->currentStep());
        self::assertSame('check_kuc', $stage->currentStep()->stepId->value);
    }

    #[Test]
    public function start_step_throws_for_unknown_step(): void
    {
        $stage = $this->createStage();

        $this->expectException(\InvalidArgumentException::class);

        $stage->startStep(new StepId('unknown'));
    }

    #[Test]
    public function starting_second_step_keeps_stage_pending(): void
    {
        $stage = $this->createStage();
        $stage->addStep($this->createStep('check_kuc'));
        $stage->addStep($this->createStep('calculate_risk', 'risk', 'calculate'));

        $stage->startStep(new StepId('check_kuc'));
        $step1 = $stage->getStep(new StepId('check_kuc'))->get();
        $step1->complete(new Outcome('clean'));

        $stage->startStep(new StepId('calculate_risk'));

        self::assertSame(Status::Pending, $stage->status());
        self::assertSame('calculate_risk', $stage->currentStep()->stepId->value);
    }

    #[Test]
    public function mark_completed_changes_status(): void
    {
        $stage = $this->createStage();
        $stage->addStep($this->createStep('check_kuc'));
        $stage->startStep(new StepId('check_kuc'));

        $stage->markCompleted();

        self::assertSame(Status::Completed, $stage->status());
    }

    #[Test]
    public function steps_returns_stream_of_all_steps(): void
    {
        $stage = $this->createStage();
        $stage->addStep($this->createStep('step_a'));
        $stage->addStep($this->createStep('step_b'));
        $stage->addStep($this->createStep('step_c'));

        $steps = $stage->steps();

        $ids = [];
        $steps->forEach(function (Step $s) use (&$ids) {
            $ids[] = $s->stepId->value;
        });

        self::assertCount(3, $ids);
        self::assertContains('step_a', $ids);
        self::assertContains('step_b', $ids);
        self::assertContains('step_c', $ids);
    }
}
