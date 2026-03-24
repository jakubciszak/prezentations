<?php

declare(strict_types=1);

namespace App\MembershipActivity\Event;

use DateTimeImmutable;

interface MemberEvent
{
    public string $memberId { get; }
    public DateTimeImmutable $occurredAt { get; }
}
