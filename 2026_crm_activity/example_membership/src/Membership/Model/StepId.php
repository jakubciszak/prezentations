<?php

declare(strict_types=1);

namespace App\Membership\Model;

final readonly class StepId
{
    public function __construct(public string $value) {}

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
