<?php

declare(strict_types=1);

namespace App\Membership\Model\Points;

/**
 * Points Ledger — the accounting book for a member's loyalty points.
 *
 * Implements the Accounting archetype: every change is an append-only entry.
 * Supports two entry statuses:
 * - Active: immediately available for spending
 * - Pending: awaiting activation (e.g., online order not yet delivered)
 *
 * Balance is always derived from entries, never stored independently.
 */
final class PointsLedger
{
    /** @var LedgerEntry[] */
    private array $entries = [];

    public function earn(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Earn amount must be positive');
        }

        $entry = new LedgerEntry(EntryType::Earn, $amount, $description, $reference);
        $this->entries[] = $entry;

        return $entry;
    }

    public function earnPending(int $amount, string $description, string $reference): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Earn amount must be positive');
        }

        $entry = new LedgerEntry(EntryType::Earn, $amount, $description, $reference, EntryStatus::Pending);
        $this->entries[] = $entry;

        return $entry;
    }

    public function earnBonus(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Bonus amount must be positive');
        }

        $entry = new LedgerEntry(EntryType::BonusEarn, $amount, $description, $reference);
        $this->entries[] = $entry;

        return $entry;
    }

    public function spend(int $amount, string $description, string $reference = ''): LedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Spend amount must be positive');
        }

        if ($amount > $this->activeBalance()) {
            throw new InsufficientPointsException($amount, $this->activeBalance());
        }

        $entry = new LedgerEntry(EntryType::Spend, -$amount, $description, $reference);
        $this->entries[] = $entry;

        return $entry;
    }

    /**
     * Activate all pending entries matching the given reference.
     *
     * @return int Number of entries activated
     */
    public function activateByReference(string $reference): int
    {
        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry->isPending() && $entry->reference === $reference) {
                $entry->activate();
                $count++;
            }
        }

        return $count;
    }

    public function activeBalance(): int
    {
        return array_sum(array_map(
            fn(LedgerEntry $e) => $e->amount,
            array_filter($this->entries, fn(LedgerEntry $e) => $e->isActive()),
        ));
    }

    public function pendingBalance(): int
    {
        return array_sum(array_map(
            fn(LedgerEntry $e) => $e->amount,
            array_filter($this->entries, fn(LedgerEntry $e) => $e->isPending()),
        ));
    }

    public function totalBalance(): int
    {
        return $this->activeBalance() + $this->pendingBalance();
    }

    /** @return LedgerEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return LedgerEntry[] */
    public function pendingEntries(): array
    {
        return array_values(array_filter($this->entries, fn(LedgerEntry $e) => $e->isPending()));
    }

    /** @return LedgerEntry[] */
    public function pendingEntriesForReference(string $reference): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(LedgerEntry $e) => $e->isPending() && $e->reference === $reference,
        ));
    }
}
