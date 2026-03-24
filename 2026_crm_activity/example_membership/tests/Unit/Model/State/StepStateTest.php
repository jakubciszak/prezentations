<?php

declare(strict_types=1);

namespace Tests\Unit\Model\State;

use App\Membership\Model\Outcome;
use App\Membership\Model\State\CompletedState;
use App\Membership\Model\State\FailedState;
use App\Membership\Model\State\IllegalStateTransitionException;
use App\Membership\Model\State\InitializedState;
use App\Membership\Model\State\PendingState;
use App\Membership\Model\Status;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepStateTest extends TestCase
{
    #[Test]
    public function initialized_can_transition_to_pending(): void
    {
        $state = new InitializedState();
        $next = $state->markPending();

        self::assertInstanceOf(PendingState::class, $next);
        self::assertSame(Status::Pending, $next->status);
    }

    #[Test]
    public function initialized_cannot_complete(): void
    {
        $this->expectException(IllegalStateTransitionException::class);
        (new InitializedState())->complete(new Outcome('valid'));
    }

    #[Test]
    public function initialized_cannot_fail(): void
    {
        $this->expectException(IllegalStateTransitionException::class);
        (new InitializedState())->fail(new Outcome('error'));
    }

    #[Test]
    public function pending_can_complete(): void
    {
        $state = new PendingState(new \DateTimeImmutable());
        $outcome = new Outcome('points_calculated', ['points' => 150]);
        $next = $state->complete($outcome);

        self::assertInstanceOf(CompletedState::class, $next);
        self::assertSame('points_calculated', $next->outcome->value);
        self::assertNotNull($next->completedAt);
    }

    #[Test]
    public function pending_can_fail(): void
    {
        $state = new PendingState(new \DateTimeImmutable());
        $next = $state->fail(new Outcome('timeout'));

        self::assertInstanceOf(FailedState::class, $next);
        self::assertSame(Status::Failed, $next->status);
    }

    #[Test]
    public function pending_cannot_mark_pending_again(): void
    {
        $this->expectException(IllegalStateTransitionException::class);
        (new PendingState(new \DateTimeImmutable()))->markPending();
    }

    #[Test]
    public function completed_cannot_transition(): void
    {
        $state = new CompletedState(
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            new Outcome('valid'),
        );

        $this->expectException(IllegalStateTransitionException::class);
        $state->markPending();
    }

    #[Test]
    public function failed_cannot_transition(): void
    {
        $state = new FailedState(
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            new Outcome('error'),
        );

        $this->expectException(IllegalStateTransitionException::class);
        $state->complete(new Outcome('valid'));
    }
}
