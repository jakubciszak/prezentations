<?php

declare(strict_types=1);

namespace Tests\Unit\Model\State;

use App\Onboarding\Model\State\CompletedState;
use App\Onboarding\Model\State\FailedState;
use App\Onboarding\Model\State\IllegalStateTransitionException;
use App\Onboarding\Model\State\InitializedState;
use App\Onboarding\Model\State\PendingState;
use App\Onboarding\Model\State\StepState;
use App\Onboarding\Model\Status;
use Tests\Factory\OutcomeFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepStateTest extends TestCase
{
    // --- given helpers ---

    private function givenInitializedState(): StepState
    {
        return new InitializedState();
    }

    private function givenPendingState(): StepState
    {
        return $this->givenInitializedState()->markPending();
    }

    private function givenCompletedState(): StepState
    {
        return $this->givenPendingState()->complete(OutcomeFactory::approved());
    }

    private function givenFailedState(): StepState
    {
        return $this->givenPendingState()->fail(OutcomeFactory::timeout());
    }

    // --- then helpers ---

    private function thenStatusIs(StepState $state, Status $expected): void
    {
        self::assertSame($expected, $state->status);
    }

    private function thenTransitionIsRejected(StepState $state, callable $action): void
    {
        $this->expectException(IllegalStateTransitionException::class);
        $action($state);
    }

    // --- InitializedState ---

    #[Test]
    public function initialized_state_has_correct_status(): void
    {
        $state = $this->givenInitializedState();

        $this->thenStatusIs($state, Status::Initialized);
    }

    #[Test]
    public function initialized_state_has_no_timestamps_or_outcome(): void
    {
        $state = $this->givenInitializedState();

        self::assertNull($state->outcome);
        self::assertNull($state->startedAt);
        self::assertNull($state->completedAt);
    }

    #[Test]
    public function initialized_transitions_to_pending(): void
    {
        $state = $this->givenInitializedState();

        $pending = $state->markPending();

        self::assertInstanceOf(PendingState::class, $pending);
        $this->thenStatusIs($pending, Status::Pending);
    }

    #[Test]
    public function initialized_cannot_complete(): void
    {
        $state = $this->givenInitializedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->complete(OutcomeFactory::approved()));
    }

    #[Test]
    public function initialized_cannot_fail(): void
    {
        $state = $this->givenInitializedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->fail(OutcomeFactory::timeout()));
    }

    // --- PendingState ---

    #[Test]
    public function pending_state_records_started_at(): void
    {
        $state = $this->givenPendingState();

        self::assertNotNull($state->startedAt);
        self::assertNull($state->completedAt);
        self::assertNull($state->outcome);
    }

    #[Test]
    public function pending_transitions_to_completed(): void
    {
        $state = $this->givenPendingState();
        $outcome = OutcomeFactory::approved(['score' => 95]);

        $completed = $state->complete($outcome);

        self::assertInstanceOf(CompletedState::class, $completed);
        $this->thenStatusIs($completed, Status::Completed);
        self::assertSame($outcome, $completed->outcome);
        self::assertNotNull($completed->startedAt);
        self::assertNotNull($completed->completedAt);
    }

    #[Test]
    public function pending_transitions_to_failed(): void
    {
        $state = $this->givenPendingState();
        $outcome = OutcomeFactory::withValue('timeout', ['reason' => 'no response']);

        $failed = $state->fail($outcome);

        self::assertInstanceOf(FailedState::class, $failed);
        $this->thenStatusIs($failed, Status::Failed);
        self::assertSame($outcome, $failed->outcome);
    }

    #[Test]
    public function pending_cannot_mark_pending_again(): void
    {
        $state = $this->givenPendingState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->markPending());
    }

    // --- CompletedState ---

    #[Test]
    public function completed_preserves_timestamps_from_pending(): void
    {
        $pending = $this->givenPendingState();
        $startedAt = $pending->startedAt;

        $completed = $pending->complete(OutcomeFactory::approved());

        self::assertEquals($startedAt, $completed->startedAt);
        self::assertGreaterThanOrEqual($startedAt, $completed->completedAt);
    }

    #[Test]
    public function completed_cannot_mark_pending(): void
    {
        $state = $this->givenCompletedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->markPending());
    }

    #[Test]
    public function completed_cannot_complete_again(): void
    {
        $state = $this->givenCompletedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->complete(OutcomeFactory::approved()));
    }

    #[Test]
    public function completed_cannot_fail(): void
    {
        $state = $this->givenCompletedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->fail(OutcomeFactory::timeout()));
    }

    // --- FailedState ---

    #[Test]
    public function failed_is_terminal_with_outcome(): void
    {
        $state = $this->givenFailedState();

        $this->thenStatusIs($state, Status::Failed);
        self::assertSame('timeout', $state->outcome->value);
    }

    #[Test]
    public function failed_cannot_mark_pending(): void
    {
        $state = $this->givenFailedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->markPending());
    }

    #[Test]
    public function failed_cannot_complete(): void
    {
        $state = $this->givenFailedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->complete(OutcomeFactory::approved()));
    }

    #[Test]
    public function failed_cannot_fail_again(): void
    {
        $state = $this->givenFailedState();

        $this->thenTransitionIsRejected($state, fn(StepState $s) => $s->fail(OutcomeFactory::timeout()));
    }

    // --- Full lifecycle ---

    #[Test]
    public function full_lifecycle_initialized_to_completed(): void
    {
        $state = $this->givenInitializedState();
        $this->thenStatusIs($state, Status::Initialized);

        $state = $state->markPending();
        $this->thenStatusIs($state, Status::Pending);
        self::assertNotNull($state->startedAt);

        $outcome = OutcomeFactory::approved(['risk_score' => 20]);
        $state = $state->complete($outcome);

        $this->thenStatusIs($state, Status::Completed);
        self::assertSame('approved', $state->outcome->value);
        self::assertSame(20, $state->outcome->metadata['risk_score']);
        self::assertNotNull($state->completedAt);
    }

    #[Test]
    public function full_lifecycle_initialized_to_failed(): void
    {
        $state = $this->givenInitializedState();
        $state = $state->markPending();

        $outcome = OutcomeFactory::withValue('service_error', ['code' => 500]);
        $state = $state->fail($outcome);

        $this->thenStatusIs($state, Status::Failed);
        self::assertSame('service_error', $state->outcome->value);
        self::assertSame(500, $state->outcome->metadata['code']);
    }
}
