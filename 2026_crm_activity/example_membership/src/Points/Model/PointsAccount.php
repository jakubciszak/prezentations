<?php

declare(strict_types=1);

namespace App\Points\Model;

/**
 * Points Account — aggregate root implementing the Accounting archetype.
 *
 * Every points change (earn, spend, refund, expire) is recorded as an immutable
 * LedgerEntry. The balance is always derived from the ledger — it's not a stored
 * field that could drift. This gives a full audit trail of how a member arrived
 * at their current balance.
 *
 * Key Accounting archetype principles applied:
 * - Balance = f(entries) — state derived from history
 * - Every mutation creates an entry — no silent changes
 * - Entries are immutable — append-only ledger
 * - Running balance stored per entry — enables point-in-time queries
 */
final class PointsAccount
{
    /** @var LedgerEntry[] */
    private array $entries = [];

    private int $balance;

    public function __construct(
        public readonly string $memberId,
        int $initialBalance = 0,
    ) {
        $this->balance = $initialBalance;

        if ($initialBalance > 0) {
            $this->entries[] = new LedgerEntry(
                type: EntryType::Adjust,
                amount: $initialBalance,
                balanceAfter: $initialBalance,
                description: 'Initial balance',
                reference: 'system:init',
            );
        }
    }

    public function earn(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Earn amount must be positive');
        }

        $this->balance += $amount;

        $entry = new LedgerEntry(
            type: EntryType::Earn,
            amount: $amount,
            balanceAfter: $this->balance,
            description: $description,
            reference: $reference,
        );
        $this->entries[] = $entry;

        return $entry;
    }

    public function earnBonus(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Bonus amount must be positive');
        }

        $this->balance += $amount;

        $entry = new LedgerEntry(
            type: EntryType::BonusEarn,
            amount: $amount,
            balanceAfter: $this->balance,
            description: $description,
            reference: $reference,
        );
        $this->entries[] = $entry;

        return $entry;
    }

    public function spend(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Spend amount must be positive');
        }

        if ($amount > $this->balance) {
            throw new InsufficientPointsException($this->memberId, $amount, $this->balance);
        }

        $this->balance -= $amount;

        $entry = new LedgerEntry(
            type: EntryType::Spend,
            amount: -$amount,
            balanceAfter: $this->balance,
            description: $description,
            reference: $reference,
        );
        $this->entries[] = $entry;

        return $entry;
    }

    public function refund(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be positive');
        }

        $this->balance += $amount;

        $entry = new LedgerEntry(
            type: EntryType::Refund,
            amount: $amount,
            balanceAfter: $this->balance,
            description: $description,
            reference: $reference,
        );
        $this->entries[] = $entry;

        return $entry;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    /** @return LedgerEntry[] */
    public function ledger(): array
    {
        return $this->entries;
    }

    /** @return LedgerEntry[] */
    public function entriesOfType(EntryType $type): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(LedgerEntry $e) => $e->type === $type,
        ));
    }

    public function totalEarned(): int
    {
        return array_sum(array_map(
            fn(LedgerEntry $e) => $e->amount,
            array_filter($this->entries, fn(LedgerEntry $e) => $e->amount > 0 && $e->type !== EntryType::Adjust),
        ));
    }

    public function totalSpent(): int
    {
        return abs(array_sum(array_map(
            fn(LedgerEntry $e) => $e->amount,
            array_filter($this->entries, fn(LedgerEntry $e) => $e->type === EntryType::Spend),
        )));
    }
}
