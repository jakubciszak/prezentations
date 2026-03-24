<?php

declare(strict_types=1);

namespace App\Points\Api;

/**
 * Public API of the Points bounded context.
 *
 * Knows nothing about Activity, MemberAccount, or any other context.
 * Accepts plain values, returns its own result DTOs.
 */
interface PointsFacade
{
    public function calculateForPurchase(float $amount, string $reference): PointsCalculation;

    public function calculateForOnlinePurchase(float $amount, string $orderId): PointsCalculation;

    public function calculateBonus(int $points, string $reference): PointsCalculation;

    public function confirmActivation(string $reference): ActivationResult;
}
