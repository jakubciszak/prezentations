<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\CaseId;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\ServiceAction;
use App\Onboarding\Model\StageId;
use App\Onboarding\Model\StepId;
use App\Onboarding\Model\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    #[Test]
    public function step_id_equality(): void
    {
        $a = new StepId('check_kuc');
        $b = new StepId('check_kuc');
        $c = new StepId('verify');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertSame('check_kuc', (string) $a);
    }

    #[Test]
    public function stage_id_equality(): void
    {
        $a = new StageId('verification');
        $b = new StageId('verification');
        $c = new StageId('documents');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertSame('verification', (string) $a);
    }

    #[Test]
    public function service_action_to_string(): void
    {
        $sa = new ServiceAction('kuc', 'check_registry');

        self::assertSame('kuc', $sa->service);
        self::assertSame('check_registry', $sa->action);
        self::assertSame('kuc.check_registry', $sa->toString());
        self::assertSame('kuc.check_registry', (string) $sa);
    }

    #[Test]
    public function outcome_with_metadata(): void
    {
        $outcome = new Outcome('approved', ['score' => 95, 'by' => 'auto']);

        self::assertSame('approved', $outcome->value);
        self::assertSame(95, $outcome->metadata['score']);
        self::assertSame('auto', $outcome->metadata['by']);
    }

    #[Test]
    public function outcome_without_metadata(): void
    {
        $outcome = new Outcome('rejected');

        self::assertSame('rejected', $outcome->value);
        self::assertEmpty($outcome->metadata);
    }

    #[Test]
    public function case_id_generates_unique_values(): void
    {
        $a = CaseId::generate();
        $b = CaseId::generate();

        self::assertNotSame($a->value, $b->value);
        self::assertNotEmpty((string) $a);
    }

    #[Test]
    public function case_id_from_string(): void
    {
        $id = CaseId::from('abc-123');

        self::assertSame('abc-123', $id->value);
        self::assertSame('abc-123', (string) $id);
    }

    #[Test]
    public function case_outcome_enum_values(): void
    {
        self::assertSame('approved', CaseOutcome::Approved->value);
        self::assertSame('rejected', CaseOutcome::Rejected->value);
        self::assertSame('abandoned', CaseOutcome::Abandoned->value);
        self::assertSame('expired', CaseOutcome::Expired->value);
        self::assertSame('requires_manual_review', CaseOutcome::RequiresManualReview->value);
    }

    #[Test]
    public function case_outcome_try_from(): void
    {
        self::assertSame(CaseOutcome::Approved, CaseOutcome::tryFrom('approved'));
        self::assertNull(CaseOutcome::tryFrom('nonexistent'));
    }

    #[Test]
    public function transition_to_next_step(): void
    {
        $t = Transition::toNextStep(new StepId('verify'));

        self::assertFalse($t->isTerminal);
        self::assertSame('verify', $t->nextStepId->value);
        self::assertNull($t->caseOutcome);
    }

    #[Test]
    public function transition_terminal(): void
    {
        $t = Transition::terminal(CaseOutcome::Rejected);

        self::assertTrue($t->isTerminal);
        self::assertNull($t->nextStepId);
        self::assertSame(CaseOutcome::Rejected, $t->caseOutcome);
    }
}
