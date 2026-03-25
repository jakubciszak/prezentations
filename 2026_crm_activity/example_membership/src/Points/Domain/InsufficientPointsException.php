<?php

declare(strict_types=1);

namespace App\Points\Domain;

final class InsufficientPointsException extends \DomainException
{
    public function __construct(
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            'Insufficient points: requested %d, available %d',
            $requested,
            $available,
        ));
    }
}
