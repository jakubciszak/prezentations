<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

final readonly class MemberOpened implements MemberEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $memberId,
        public string $name,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
