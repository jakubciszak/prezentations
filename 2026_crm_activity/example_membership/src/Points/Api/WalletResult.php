<?php

declare(strict_types=1);

namespace App\Points\Api;

/**
 * Result of a wallet operation — how many points were affected and current balances.
 */
final readonly class WalletResult
{
    public function __construct(
        public int $pointsAffected,
        public int $activeBalance,
        public int $pendingBalance,
    ) {}
}
