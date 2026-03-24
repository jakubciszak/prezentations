<?php

declare(strict_types=1);

namespace App\Points\Application;

use App\Points\Api\ActivationResult;
use App\Points\Api\PointsCalculation;
use App\Points\Api\PointsFacade;

/**
 * Default implementation — business logic for points: multipliers, bonuses, etc.
 * Zero knowledge of Activity or any other bounded context.
 */
final class DefaultPointsFacade implements PointsFacade
{
    public function calculateForPurchase(float $amount, string $reference): PointsCalculation
    {
        return new PointsCalculation(
            points: (int) floor($amount),
            pending: false,
            reference: $reference,
        );
    }

    public function calculateForOnlinePurchase(float $amount, string $orderId): PointsCalculation
    {
        return new PointsCalculation(
            points: (int) floor($amount * 1.5),
            pending: true,
            reference: $orderId,
        );
    }

    public function calculateBonus(int $points, string $reference): PointsCalculation
    {
        return new PointsCalculation(
            points: $points,
            pending: false,
            reference: $reference,
        );
    }

    public function confirmActivation(string $reference): ActivationResult
    {
        // In production: verify with shipping API, check expiration, etc.
        return new ActivationResult(reference: $reference);
    }
}
