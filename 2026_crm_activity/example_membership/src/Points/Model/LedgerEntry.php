<?php

declare(strict_types=1);

namespace App\Points\Model;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * A single ledger entry in the points account.
 * Follows the Accounting archetype: every balance change is an immutable entry
 * with a running balance snapshot.
 */
final readonly class LedgerEntry
{
    public string $id;
    public DateTimeImmutable $createdAt;

    public function __construct(
        public EntryType $type,
        public int $amount,
        public int $balanceAfter,
        public string $description,
        public string $reference = '',
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = new DateTimeImmutable();
    }
}
