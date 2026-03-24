<?php

declare(strict_types=1);

namespace App\Membership\Model\Points;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Immutable ledger entry following the Accounting archetype.
 *
 * Each entry records a points change with its status (Active or Pending).
 * Pending entries await external activation (e.g., package delivery for online orders).
 */
final class LedgerEntry
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    private EntryStatus $status;

    public function __construct(
        public readonly EntryType $type,
        public readonly int $amount,
        public readonly string $description,
        public readonly string $reference,
        EntryStatus $status = EntryStatus::Active,
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = new DateTimeImmutable();
        $this->status = $status;
    }

    public function status(): EntryStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === EntryStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === EntryStatus::Pending;
    }

    public function activate(): void
    {
        if ($this->status !== EntryStatus::Pending) {
            throw new \DomainException("Cannot activate entry in status '{$this->status->value}'");
        }
        $this->status = EntryStatus::Active;
    }
}
