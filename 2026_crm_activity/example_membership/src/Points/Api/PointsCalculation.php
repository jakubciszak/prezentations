<?php

declare(strict_types=1);

namespace App\Points\Api;

/**
 * Result of a points calculation — how many points and whether they're pending.
 */
final readonly class PointsCalculation
{
    public function __construct(
        public int $points,
        public bool $pending,
        public string $reference,
    ) {}
}
