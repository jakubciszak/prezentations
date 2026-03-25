<?php

declare(strict_types=1);

namespace App\MembershipActivity\Domain;

use Ramsey\Uuid\Uuid;

final readonly class ActivityId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
