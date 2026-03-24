<?php

declare(strict_types=1);

namespace Tests\Unit\Points;

use App\Points\Model\EntryType;
use App\Points\Model\InsufficientPointsException;
use App\Points\Model\PointsAccount;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointsAccountTest extends TestCase
{
    #[Test]
    public function new_account_with_zero_balance(): void
    {
        $account = new PointsAccount('MBR-001');

        self::assertSame(0, $account->balance());
        self::assertEmpty($account->ledger());
    }

    #[Test]
    public function new_account_with_initial_balance_creates_adjust_entry(): void
    {
        $account = new PointsAccount('MBR-001', 5_000);

        self::assertSame(5_000, $account->balance());
        self::assertCount(1, $account->ledger());

        $entry = $account->ledger()[0];
        self::assertSame(EntryType::Adjust, $entry->type);
        self::assertSame(5_000, $entry->amount);
        self::assertSame(5_000, $entry->balanceAfter);
    }

    #[Test]
    public function earn_adds_points_and_creates_entry(): void
    {
        $account = new PointsAccount('MBR-001');

        $entry = $account->earn(150, 'Purchase: 150 PLN', 'TXN-001');

        self::assertSame(150, $account->balance());
        self::assertSame(EntryType::Earn, $entry->type);
        self::assertSame(150, $entry->amount);
        self::assertSame(150, $entry->balanceAfter);
        self::assertSame('Purchase: 150 PLN', $entry->description);
        self::assertSame('TXN-001', $entry->reference);
    }

    #[Test]
    public function earn_bonus_creates_bonus_entry(): void
    {
        $account = new PointsAccount('MBR-001', 1_000);

        $entry = $account->earnBonus(75, 'Online bonus', 'TXN-002');

        self::assertSame(1_075, $account->balance());
        self::assertSame(EntryType::BonusEarn, $entry->type);
        self::assertSame(75, $entry->amount);
    }

    #[Test]
    public function spend_deducts_points_and_creates_negative_entry(): void
    {
        $account = new PointsAccount('MBR-001', 5_000);

        $entry = $account->spend(2_000, 'Reward: 20% coupon', 'reward:RWD-20PCT');

        self::assertSame(3_000, $account->balance());
        self::assertSame(EntryType::Spend, $entry->type);
        self::assertSame(-2_000, $entry->amount);
        self::assertSame(3_000, $entry->balanceAfter);
    }

    #[Test]
    public function spend_throws_when_insufficient_balance(): void
    {
        $account = new PointsAccount('MBR-001', 100);

        $this->expectException(InsufficientPointsException::class);
        $this->expectExceptionMessage("requested 500, available 100");

        $account->spend(500, 'Too expensive');
    }

    #[Test]
    public function refund_restores_points(): void
    {
        $account = new PointsAccount('MBR-001', 3_000);
        $account->spend(1_000, 'Reward purchase');

        $entry = $account->refund(1_000, 'Refund: failed reward', 'refund:RWD-001');

        self::assertSame(3_000, $account->balance());
        self::assertSame(EntryType::Refund, $entry->type);
        self::assertSame(1_000, $entry->amount);
    }

    #[Test]
    public function ledger_preserves_all_entries_in_order(): void
    {
        $account = new PointsAccount('MBR-001', 1_000);
        $account->earn(200, 'Purchase 1');
        $account->earnBonus(100, 'Bonus');
        $account->spend(500, 'Reward');

        $ledger = $account->ledger();
        self::assertCount(4, $ledger); // init + 3 operations

        $types = array_map(fn($e) => $e->type, $ledger);
        self::assertSame([EntryType::Adjust, EntryType::Earn, EntryType::BonusEarn, EntryType::Spend], $types);

        $balances = array_map(fn($e) => $e->balanceAfter, $ledger);
        self::assertSame([1_000, 1_200, 1_300, 800], $balances);
    }

    #[Test]
    public function entries_of_type_filters_correctly(): void
    {
        $account = new PointsAccount('MBR-001');
        $account->earn(100, 'A');
        $account->earn(200, 'B');
        $account->earnBonus(50, 'C');

        self::assertCount(2, $account->entriesOfType(EntryType::Earn));
        self::assertCount(1, $account->entriesOfType(EntryType::BonusEarn));
        self::assertCount(0, $account->entriesOfType(EntryType::Spend));
    }

    #[Test]
    public function total_earned_and_total_spent(): void
    {
        $account = new PointsAccount('MBR-001', 5_000);
        $account->earn(300, 'Purchase');
        $account->earnBonus(100, 'Bonus');
        $account->spend(1_000, 'Reward');
        $account->spend(500, 'Reward 2');

        self::assertSame(400, $account->totalEarned());
        self::assertSame(1_500, $account->totalSpent());
        self::assertSame(3_900, $account->balance());
    }

    #[Test]
    public function earn_with_zero_throws(): void
    {
        $account = new PointsAccount('MBR-001');

        $this->expectException(\InvalidArgumentException::class);
        $account->earn(0, 'Invalid');
    }

    #[Test]
    public function spend_with_zero_throws(): void
    {
        $account = new PointsAccount('MBR-001', 100);

        $this->expectException(\InvalidArgumentException::class);
        $account->spend(0, 'Invalid');
    }

    #[Test]
    public function insufficient_points_exception_carries_details(): void
    {
        $account = new PointsAccount('MBR-042', 50);

        try {
            $account->spend(200, 'Too much');
            self::fail('Expected InsufficientPointsException');
        } catch (InsufficientPointsException $e) {
            self::assertSame('MBR-042', $e->memberId);
            self::assertSame(200, $e->requested);
            self::assertSame(50, $e->available);
        }
    }
}
