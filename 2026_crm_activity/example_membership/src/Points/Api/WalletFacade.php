<?php

declare(strict_types=1);

namespace App\Points\Api;

use App\Points\Domain\InsufficientPointsException;

/**
 * Public API for managing member wallets (points ledgers).
 *
 * Each member has an independent wallet. The Points context
 * owns all ledger accounting — no other context touches entries directly.
 */
interface WalletFacade
{
    public function earn(string $memberId, int $points, string $description, string $reference): WalletResult;

    public function earnPending(string $memberId, int $points, string $description, string $reference): WalletResult;

    /**
     * @throws \DomainException if reference has no pending entries
     */
    public function activateByReference(string $memberId, string $reference): WalletResult;

    /**
     * @throws InsufficientPointsException
     */
    public function spend(string $memberId, int $points, string $description, string $reference): WalletResult;

    public function getBalance(string $memberId): WalletBalance;
}
