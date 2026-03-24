<?php

declare(strict_types=1);

namespace App\Points\Application;

use App\Points\Api\WalletBalance;
use App\Points\Api\WalletFacade;
use App\Points\Api\WalletResult;
use App\Points\Domain\PointsLedger;

/**
 * In-memory wallet management — one PointsLedger per member.
 *
 * In production, this would be backed by a database.
 * The PointsLedger (accounting archetype) lives entirely within the Points context.
 */
final class DefaultWalletService implements WalletFacade
{
    /** @var array<string, PointsLedger> */
    private array $wallets = [];

    private function walletFor(string $memberId): PointsLedger
    {
        return $this->wallets[$memberId] ??= new PointsLedger();
    }

    public function earn(string $memberId, int $points, string $description, string $reference): WalletResult
    {
        $ledger = $this->walletFor($memberId);
        $ledger->earn($points, $description, $reference);

        return new WalletResult($points, $ledger->activeBalance(), $ledger->pendingBalance());
    }

    public function earnPending(string $memberId, int $points, string $description, string $reference): WalletResult
    {
        $ledger = $this->walletFor($memberId);
        $ledger->earnPending($points, $description, $reference);

        return new WalletResult($points, $ledger->activeBalance(), $ledger->pendingBalance());
    }

    public function activateByReference(string $memberId, string $reference): WalletResult
    {
        $ledger = $this->walletFor($memberId);

        $pending = $ledger->pendingEntriesForReference($reference);
        $pointsActivated = array_sum(array_map(fn($e) => $e->amount, $pending));

        $ledger->activateByReference($reference);

        return new WalletResult($pointsActivated, $ledger->activeBalance(), $ledger->pendingBalance());
    }

    public function spend(string $memberId, int $points, string $description, string $reference): WalletResult
    {
        $ledger = $this->walletFor($memberId);
        $ledger->spend($points, $description, $reference);

        return new WalletResult($points, $ledger->activeBalance(), $ledger->pendingBalance());
    }

    public function getBalance(string $memberId): WalletBalance
    {
        $ledger = $this->walletFor($memberId);

        return new WalletBalance(
            active: $ledger->activeBalance(),
            pending: $ledger->pendingBalance(),
            total: $ledger->totalBalance(),
        );
    }
}
