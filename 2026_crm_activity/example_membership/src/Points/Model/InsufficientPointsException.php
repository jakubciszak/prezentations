<?php

declare(strict_types=1);

namespace App\Points\Model;

final class InsufficientPointsException extends \DomainException
{
    public function __construct(
        public readonly string $memberId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            "Insufficient points for member '%s': requested %d, available %d",
            $memberId,
            $requested,
            $available,
        ));
    }
}
