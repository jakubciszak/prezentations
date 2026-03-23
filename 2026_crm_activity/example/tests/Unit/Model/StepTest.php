<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\ServiceAction;
use App\Onboarding\Model\State\IllegalStateTransitionException;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\Step;
use App\Onboarding\Model\StepId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepTest extends TestCase
{
    private function createStep(string $id = 'check_kuc'): Step
    {
        return new Step(
            stepId: new StepId($id),
            name: 'KUC Registry Check',
            serviceAction: new ServiceAction('kuc', 'check_registry'),
        );
    }

    #[Test]
    public function new_step_is_initialized(): void
    {
        $step = $this->createStep();

        self::assertSame(Status::Initialized, $step->status());
        self::assertNull($step->outcome());
        self::assertNull($step->startedAt());
        self::assertNull($step->completedAt());
    }

    #[Test]
    public function step_exposes_identity_and_service_action(): void
    {
        $step = $this->createStep('verify_nip');

        self::assertSame('verify_nip', $step->stepId->value);
        self::assertSame('kuc', $step->serviceAction->service);
        self::assertSame('check_registry', $step->serviceAction->action);
        self::assertSame('kuc.check_registry', $step->serviceAction->toString());
    }

    #[Test]
    public function mark_pending_transitions_step(): void
    {
        $step = $this->createStep();

        $step->markPending();

        self::assertSame(Status::Pending, $step->status());
        self::assertNotNull($step->startedAt());
    }

    #[Test]
    public function complete_with_outcome(): void
    {
        $step = $this->createStep();
        $step->markPending();

        $outcome = new Outcome('clean', ['nip' => '5261234567']);
        $step->complete($outcome);

        self::assertSame(Status::Completed, $step->status());
        self::assertSame('clean', $step->outcome()->value);
        self::assertSame('5261234567', $step->outcome()->metadata['nip']);
        self::assertNotNull($step->completedAt());
    }

    #[Test]
    public function fail_with_outcome(): void
    {
        $step = $this->createStep();
        $step->markPending();

        $outcome = new Outcome('timeout');
        $step->fail($outcome);

        self::assertSame(Status::Failed, $step->status());
        self::assertSame('timeout', $step->outcome()->value);
    }

    #[Test]
    public function cannot_complete_without_pending_first(): void
    {
        $step = $this->createStep();

        $this->expectException(IllegalStateTransitionException::class);

        $step->complete(new Outcome('clean'));
    }

    #[Test]
    public function cannot_fail_without_pending_first(): void
    {
        $step = $this->createStep();

        $this->expectException(IllegalStateTransitionException::class);

        $step->fail(new Outcome('error'));
    }

    #[Test]
    public function cannot_mark_pending_twice(): void
    {
        $step = $this->createStep();
        $step->markPending();

        $this->expectException(IllegalStateTransitionException::class);

        $step->markPending();
    }

    #[Test]
    public function cannot_complete_after_completed(): void
    {
        $step = $this->createStep();
        $step->markPending();
        $step->complete(new Outcome('clean'));

        $this->expectException(IllegalStateTransitionException::class);

        $step->complete(new Outcome('flagged'));
    }
}
