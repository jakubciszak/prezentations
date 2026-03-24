<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Model\Stage;
use App\Membership\Model\StageId;
use App\Membership\Model\Status;
use App\Membership\Model\StepId;
use Tests\Factory\StageBuilder;
use Tests\Factory\StepFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    #[Test]
    public function new_stage_is_initialized(): void
    {
        $stage = new Stage(new StageId('points_processing'), 'Points Processing');

        self::assertSame(Status::Initialized, $stage->status());
        self::assertNull($stage->currentStep());
    }

    #[Test]
    public function adding_and_retrieving_steps(): void
    {
        $step = StepFactory::initialized('validate_transaction');
        $stage = StageBuilder::aStage()->withStep($step)->build();

        self::assertTrue($stage->hasStep(new StepId('validate_transaction')));
        self::assertFalse($stage->hasStep(new StepId('nonexistent')));
        self::assertSame($step, $stage->getStep(new StepId('validate_transaction'))->get());
    }

    #[Test]
    public function starting_step_changes_stage_to_pending(): void
    {
        $stage = StageBuilder::aStage()
            ->withStep(StepFactory::initialized('validate_transaction'))
            ->build();

        $stage->startStep(new StepId('validate_transaction'));

        self::assertSame(Status::Pending, $stage->status());
        self::assertNotNull($stage->currentStep());
        self::assertSame(Status::Pending, $stage->currentStep()->status());
    }

    #[Test]
    public function mark_completed_changes_status(): void
    {
        $stage = StageBuilder::aStage()
            ->withStep(StepFactory::initialized('validate_transaction'))
            ->withStartedStep('validate_transaction')
            ->build();

        $stage->markCompleted();

        self::assertSame(Status::Completed, $stage->status());
    }

    #[Test]
    public function steps_returns_all_steps_as_stream(): void
    {
        $stage = StageBuilder::aStage()
            ->withSteps(
                StepFactory::initialized('validate_transaction'),
                StepFactory::initialized('calculate_points', 'Calculate Points', 'points', 'calculate'),
            )
            ->build();

        $ids = $stage->steps()->map(fn($s) => $s->stepId->value)->toArray();

        self::assertSame(['validate_transaction', 'calculate_points'], $ids);
    }
}
