<?php

declare(strict_types=1);

namespace Tests\Unit\Model\State;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\State\CompletedState;
use App\Onboarding\Model\State\FailedState;
use App\Onboarding\Model\State\IllegalStateTransitionException;
use App\Onboarding\Model\State\InitializedState;
use App\Onboarding\Model\State\PendingState;
use App\Onboarding\Model\Status;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepStateTest extends TestCase
{
    // --- InitializedState ---

    #[Test]
    public function initialized_state_has_correct_status(): void
    {
        $state = new InitializedState();

        self::assertSame(Status::Initialized, $state->status());
    }

    #[Test]
    public function initialized_state_has_no_timestamps_or_outcome(): void
    {
        $state = new InitializedState();

        self::assertNull($state->outcome());
        self::assertNull($state->startedAt());
        self::assertNull($state->completedAt());
    }

    #[Test]
    public function initialized_transitions_to_pending(): void
    {
        $state = new InitializedState();

        $pending = $state->markPending();

        self::assertInstanceOf(PendingState::class, $pending);
        self::assertSame(Status::Pending, $pending->status());
    }

    #[Test]
    public function initialized_cannot_complete(): void
    {
        $state = new InitializedState();

        $this->expectException(IllegalStateTransitionException::class);
        $this->expectExceptionMessage("Cannot 'complete' step in state 'initialized'");

        $state->complete(new Outcome('done'));
    }

    #[Test]
    public function initialized_cannot_fail(): void
    {
        $state = new InitializedState();

        $this->expectException(IllegalStateTransitionException::class);

        $state->fail(new Outcome('error'));
    }

    // --- PendingState ---

    #[Test]
    public function pending_state_records_started_at(): void
    {
        $pending = (new InitializedState())->markPending();

        self::assertNotNull($pending->startedAt());
        self::assertNull($pending->completedAt());
        self::assertNull($pending->outcome());
    }

    #[Test]
    public function pending_transitions_to_completed(): void
    {
        $pending = (new InitializedState())->markPending();
        $outcome = new Outcome('approved', ['score' => 95]);

        $completed = $pending->complete($outcome);

        self::assertInstanceOf(CompletedState::class, $completed);
        self::assertSame(Status::Completed, $completed->status());
        self::assertSame($outcome, $completed->outcome());
        self::assertNotNull($completed->startedAt());
        self::assertNotNull($completed->completedAt());
    }

    #[Test]
    public function pending_transitions_to_failed(): void
    {
        $pending = (new InitializedState())->markPending();
        $outcome = new Outcome('timeout', ['reason' => 'no response']);

        $failed = $pending->fail($outcome);

        self::assertInstanceOf(FailedState::class, $failed);
        self::assertSame(Status::Failed, $failed->status());
        self::assertSame($outcome, $failed->outcome());
    }

    #[Test]
    public function pending_cannot_mark_pending_again(): void
    {
        $pending = (new InitializedState())->markPending();

        $this->expectException(IllegalStateTransitionException::class);
        $this->expectExceptionMessage("Cannot 'markPending' step in state 'pending'");

        $pending->markPending();
    }

    // --- CompletedState ---

    #[Test]
    public function completed_preserves_timestamps_from_pending(): void
    {
        $pending = (new InitializedState())->markPending();
        $startedAt = $pending->startedAt();

        $completed = $pending->complete(new Outcome('done'));

        self::assertEquals($startedAt, $completed->startedAt());
        self::assertGreaterThanOrEqual($startedAt, $completed->completedAt());
    }

    #[Test]
    public function completed_is_terminal_cannot_transition(): void
    {
        $completed = (new InitializedState())
            ->markPending()
            ->complete(new Outcome('done'));

        $this->expectException(IllegalStateTransitionException::class);
        $completed->markPending();
    }

    #[Test]
    public function completed_cannot_complete_again(): void
    {
        $completed = (new InitializedState())
            ->markPending()
            ->complete(new Outcome('done'));

        $this->expectException(IllegalStateTransitionException::class);
        $completed->complete(new Outcome('again'));
    }

    #[Test]
    public function completed_cannot_fail(): void
    {
        $completed = (new InitializedState())
            ->markPending()
            ->complete(new Outcome('done'));

        $this->expectException(IllegalStateTransitionException::class);
        $completed->fail(new Outcome('error'));
    }

    // --- FailedState ---

    #[Test]
    public function failed_is_terminal_cannot_transition(): void
    {
        $failed = (new InitializedState())
            ->markPending()
            ->fail(new Outcome('timeout'));

        self::assertSame(Status::Failed, $failed->status());
        self::assertSame('timeout', $failed->outcome()->value);

        $this->expectException(IllegalStateTransitionException::class);
        $failed->markPending();
    }

    #[Test]
    public function failed_cannot_complete(): void
    {
        $failed = (new InitializedState())
            ->markPending()
            ->fail(new Outcome('timeout'));

        $this->expectException(IllegalStateTransitionException::class);
        $failed->complete(new Outcome('done'));
    }

    #[Test]
    public function failed_cannot_fail_again(): void
    {
        $failed = (new InitializedState())
            ->markPending()
            ->fail(new Outcome('timeout'));

        $this->expectException(IllegalStateTransitionException::class);
        $failed->fail(new Outcome('another'));
    }

    // --- Full lifecycle ---

    #[Test]
    public function full_lifecycle_initialized_to_completed(): void
    {
        $state = new InitializedState();
        self::assertSame(Status::Initialized, $state->status());

        $state = $state->markPending();
        self::assertSame(Status::Pending, $state->status());
        self::assertNotNull($state->startedAt());

        $outcome = new Outcome('approved', ['risk_score' => 20]);
        $state = $state->complete($outcome);
        self::assertSame(Status::Completed, $state->status());
        self::assertSame('approved', $state->outcome()->value);
        self::assertSame(20, $state->outcome()->metadata['risk_score']);
        self::assertNotNull($state->completedAt());
    }

    #[Test]
    public function full_lifecycle_initialized_to_failed(): void
    {
        $state = new InitializedState();
        $state = $state->markPending();

        $outcome = new Outcome('service_error', ['code' => 500]);
        $state = $state->fail($outcome);

        self::assertSame(Status::Failed, $state->status());
        self::assertSame('service_error', $state->outcome()->value);
        self::assertSame(500, $state->outcome()->metadata['code']);
    }
}
