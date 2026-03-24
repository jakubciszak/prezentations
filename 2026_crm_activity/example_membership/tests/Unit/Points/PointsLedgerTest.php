<?php

declare(strict_types=1);

namespace Tests\Unit\Points;

use App\Membership\Model\Points\EntryStatus;
use App\Membership\Model\Points\EntryType;
use App\Membership\Model\Points\InsufficientPointsException;
use App\Membership\Model\Points\PointsLedger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointsLedgerTest extends TestCase
{
    #[Test]
    public function empty_ledger_has_zero_balance(): void
    {
        $ledger = new PointsLedger();

        self::assertSame(0, $ledger->activeBalance());
        self::assertSame(0, $ledger->pendingBalance());
        self::assertSame(0, $ledger->totalBalance());
    }

    #[Test]
    public function earn_adds_active_points(): void
    {
        $ledger = new PointsLedger();
        $entry = $ledger->earn(150, 'Purchase', 'TXN-001');

        self::assertSame(150, $ledger->activeBalance());
        self::assertSame(EntryType::Earn, $entry->type);
        self::assertTrue($entry->isActive());
        self::assertSame('TXN-001', $entry->reference);
    }

    #[Test]
    public function earn_pending_does_not_affect_active_balance(): void
    {
        $ledger = new PointsLedger();
        $entry = $ledger->earnPending(300, 'Online purchase', 'ORD-001');

        self::assertSame(0, $ledger->activeBalance());
        self::assertSame(300, $ledger->pendingBalance());
        self::assertSame(300, $ledger->totalBalance());
        self::assertTrue($entry->isPending());
    }

    #[Test]
    public function activate_by_reference_moves_pending_to_active(): void
    {
        $ledger = new PointsLedger();
        $ledger->earnPending(300, 'Online purchase', 'ORD-001');
        $ledger->earn(100, 'Store purchase', 'TXN-001');

        $count = $ledger->activateByReference('ORD-001');

        self::assertSame(1, $count);
        self::assertSame(400, $ledger->activeBalance());
        self::assertSame(0, $ledger->pendingBalance());
    }

    #[Test]
    public function activate_does_not_affect_other_references(): void
    {
        $ledger = new PointsLedger();
        $ledger->earnPending(300, 'Order 1', 'ORD-001');
        $ledger->earnPending(200, 'Order 2', 'ORD-002');

        $ledger->activateByReference('ORD-001');

        self::assertSame(300, $ledger->activeBalance());
        self::assertSame(200, $ledger->pendingBalance());
    }

    #[Test]
    public function spend_deducts_from_active_balance(): void
    {
        $ledger = new PointsLedger();
        $ledger->earn(1_000, 'Purchase');
        $entry = $ledger->spend(400, 'Reward', 'reward:RWD-01');

        self::assertSame(600, $ledger->activeBalance());
        self::assertSame(-400, $entry->amount);
        self::assertSame(EntryType::Spend, $entry->type);
    }

    #[Test]
    public function spend_cannot_exceed_active_balance(): void
    {
        $ledger = new PointsLedger();
        $ledger->earn(100, 'Purchase');
        $ledger->earnPending(500, 'Online', 'ORD-001');

        $this->expectException(InsufficientPointsException::class);
        $ledger->spend(200, 'Too much');
    }

    #[Test]
    public function spend_ignores_pending_balance(): void
    {
        $ledger = new PointsLedger();
        $ledger->earnPending(1_000, 'Online order', 'ORD-001');

        $this->expectException(InsufficientPointsException::class);
        $ledger->spend(100, 'Should fail');
    }

    #[Test]
    public function earn_bonus_creates_bonus_entry(): void
    {
        $ledger = new PointsLedger();
        $entry = $ledger->earnBonus(500, 'Challenge', 'CHALLENGE-01');

        self::assertSame(500, $ledger->activeBalance());
        self::assertSame(EntryType::BonusEarn, $entry->type);
    }

    #[Test]
    public function ledger_preserves_all_entries(): void
    {
        $ledger = new PointsLedger();
        $ledger->earn(100, 'A');
        $ledger->earnPending(200, 'B', 'ORD-1');
        $ledger->earnBonus(50, 'C');
        $ledger->spend(30, 'D');

        self::assertCount(4, $ledger->entries());
    }

    #[Test]
    public function pending_entries_for_reference(): void
    {
        $ledger = new PointsLedger();
        $ledger->earnPending(100, 'A', 'ORD-1');
        $ledger->earnPending(200, 'B', 'ORD-2');
        $ledger->earnPending(50, 'C', 'ORD-1');

        $pending = $ledger->pendingEntriesForReference('ORD-1');
        self::assertCount(2, $pending);
    }

    #[Test]
    public function earn_zero_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PointsLedger())->earn(0, 'Invalid');
    }
}
