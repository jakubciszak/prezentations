<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Model\Status;
use Tests\Factory\OutcomeFactory;
use Tests\Factory\StepFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepTest extends TestCase
{
    #[Test]
    public function new_step_is_initialized(): void
    {
        $step = StepFactory::initialized();

        self::assertSame(Status::Initialized, $step->status());
        self::assertNull($step->outcome());
        self::assertNull($step->startedAt());
        self::assertNull($step->completedAt());
    }

    #[Test]
    public function pending_step_has_started_at(): void
    {
        $step = StepFactory::pending();

        self::assertSame(Status::Pending, $step->status());
        self::assertNotNull($step->startedAt());
        self::assertNull($step->completedAt());
    }

    #[Test]
    public function completed_step_has_outcome_and_timestamps(): void
    {
        $step = StepFactory::completed('validate_transaction', 'valid');

        self::assertSame(Status::Completed, $step->status());
        self::assertSame('valid', $step->outcome()->value);
        self::assertNotNull($step->startedAt());
        self::assertNotNull($step->completedAt());
    }

    #[Test]
    public function failed_step_has_outcome_and_timestamps(): void
    {
        $step = StepFactory::failed('validate_transaction', 'timeout');

        self::assertSame(Status::Failed, $step->status());
        self::assertSame('timeout', $step->outcome()->value);
        self::assertNotNull($step->startedAt());
        self::assertNotNull($step->completedAt());
    }

    #[Test]
    public function step_preserves_service_action(): void
    {
        $step = StepFactory::initialized('validate_transaction', 'Validate Transaction', 'transaction', 'validate');

        self::assertSame('transaction', $step->serviceAction->service);
        self::assertSame('validate', $step->serviceAction->action);
        self::assertSame('transaction.validate', $step->serviceAction->toString());
    }
}
