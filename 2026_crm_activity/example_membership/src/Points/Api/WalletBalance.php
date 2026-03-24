<?php

declare(strict_types=1);

namespace App\Points\Api;

/**
 * Read-only snapshot of a member's wallet balance.
 */
final readonly class WalletBalance
{
    public function __construct(
        public int $active,
        public int $pending,
        public int $total,
    ) {}
}
