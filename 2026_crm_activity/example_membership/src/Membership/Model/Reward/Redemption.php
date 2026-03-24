<?php

declare(strict_types=1);

namespace App\Membership\Model\Reward;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final readonly class Redemption
{
    public string $id;
    public DateTimeImmutable $redeemedAt;

    public function __construct(
        public Reward $reward,
        public int $pointsSpent,
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->redeemedAt = new DateTimeImmutable();
    }
}
