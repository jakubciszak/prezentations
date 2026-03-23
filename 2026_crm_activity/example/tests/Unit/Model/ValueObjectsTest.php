<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Onboarding\Model\CaseId;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\ServiceAction;
use App\Onboarding\Model\StageId;
use App\Onboarding\Model\StepId;
use App\Onboarding\Model\Transition;
use Tests\Factory\OutcomeFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    // --- given helpers ---

    private function givenStepId(string $value = 'check_kuc'): StepId
    {
        return new StepId($value);
    }

    private function givenStageId(string $value = 'verification'): StageId
    {
        return new StageId($value);
    }

    private function givenServiceAction(string $service = 'kuc', string $action = 'check_registry'): ServiceAction
    {
        return new ServiceAction($service, $action);
    }

    // --- then helpers ---

    private function thenIdsAreEqual(StepId|StageId $a, StepId|StageId $b): void
    {
        self::assertTrue($a->equals($b));
    }

    private function thenIdsAreNotEqual(StepId|StageId $a, StepId|StageId $b): void
    {
        self::assertFalse($a->equals($b));
    }

    // --- StepId ---

    #[Test]
    public function step_id_equality(): void
    {
        $a = $this->givenStepId('check_kuc');
        $b = $this->givenStepId('check_kuc');
        $c = $this->givenStepId('verify');

        $this->thenIdsAreEqual($a, $b);
        $this->thenIdsAreNotEqual($a, $c);
        self::assertSame('check_kuc', (string) $a);
    }

    // --- StageId ---

    #[Test]
    public function stage_id_equality(): void
    {
        $a = $this->givenStageId('verification');
        $b = $this->givenStageId('verification');
        $c = $this->givenStageId('documents');

        $this->thenIdsAreEqual($a, $b);
        $this->thenIdsAreNotEqual($a, $c);
        self::assertSame('verification', (string) $a);
    }

    // --- ServiceAction ---

    #[Test]
    public function service_action_to_string(): void
    {
        $sa = $this->givenServiceAction('kuc', 'check_registry');

        self::assertSame('kuc', $sa->service);
        self::assertSame('check_registry', $sa->action);
        self::assertSame('kuc.check_registry', $sa->toString());
        self::assertSame('kuc.check_registry', (string) $sa);
    }

    // --- Outcome ---

    #[Test]
    public function outcome_with_metadata(): void
    {
        $outcome = OutcomeFactory::approved(['score' => 95, 'by' => 'auto']);

        self::assertSame('approved', $outcome->value);
        self::assertSame(95, $outcome->metadata['score']);
        self::assertSame('auto', $outcome->metadata['by']);
    }

    #[Test]
    public function outcome_without_metadata(): void
    {
        $outcome = OutcomeFactory::rejected();

        self::assertSame('rejected', $outcome->value);
        self::assertEmpty($outcome->metadata);
    }

    // --- CaseId ---

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

    // --- CaseOutcome enum ---

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

    // --- Transition ---

    #[Test]
    public function transition_to_next_step(): void
    {
        $stepId = $this->givenStepId('verify');

        $t = Transition::toNextStep($stepId);

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
