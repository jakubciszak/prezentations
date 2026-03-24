<?php

declare(strict_types=1);

namespace App\Membership\Model;

final readonly class Outcome
{
    public function __construct(
        public string $value,
        public array $metadata = [],
    ) {}
}
