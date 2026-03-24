<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Membership\Model\CaseId;
use App\Membership\Model\ServiceAction;
use App\Membership\Model\StageId;
use App\Membership\Model\StepId;
use App\Membership\Model\Transition;
use App\Membership\Model\CaseOutcome;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    #[Test]
    public function case_id_generates_unique_values(): void
    {
        $id1 = CaseId::generate();
        $id2 = CaseId::generate();

        self::assertNotSame($id1->value, $id2->value);
    }

    #[Test]
    public function case_id_can_be_created_from_string(): void
    {
        $id = CaseId::from('test-id');
        self::assertSame('test-id', $id->value);
        self::assertSame('test-id', (string) $id);
    }

    #[Test]
    public function step_id_equality(): void
    {
        $id1 = new StepId('validate_transaction');
        $id2 = new StepId('validate_transaction');
        $id3 = new StepId('calculate_points');

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));
    }

    #[Test]
    public function stage_id_equality(): void
    {
        $id1 = new StageId('points_processing');
        $id2 = new StageId('points_processing');

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function service_action_to_string(): void
    {
        $action = new ServiceAction('points', 'calculate');

        self::assertSame('points.calculate', $action->toString());
        self::assertSame('points.calculate', (string) $action);
    }

    #[Test]
    public function transition_to_next_step(): void
    {
        $transition = Transition::toNextStep(new StepId('calculate_points'));

        self::assertFalse($transition->isTerminal);
        self::assertSame('calculate_points', $transition->nextStepId->value);
        self::assertNull($transition->caseOutcome);
    }

    #[Test]
    public function transition_terminal(): void
    {
        $transition = Transition::terminal(CaseOutcome::PointsAwarded);

        self::assertTrue($transition->isTerminal);
        self::assertNull($transition->nextStepId);
        self::assertSame(CaseOutcome::PointsAwarded, $transition->caseOutcome);
    }
}
