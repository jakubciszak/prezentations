<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the transaction validation service.
 * In production this would call a payment gateway or POS system.
 */
final readonly class TransactionServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function validate(string $caseId, string $stepId, array $context): void
    {
        // Simulate: amount <= 0 is invalid, amount > 5000 is fraud-suspected, rest valid
        $amount = $context['amount'] ?? 0;
        $outcome = match (true) {
            $amount <= 0 => 'invalid',
            $amount > 5000 => 'fraud_suspected',
            default => 'valid',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'transaction',
            action: 'validate',
            outcome: $outcome,
            metadata: [
                'transaction_id' => $context['transaction_id'] ?? 'unknown',
                'amount' => $amount,
                'validated_at' => date('c'),
            ],
        ));
    }

    public function validateOnline(string $caseId, string $stepId, array $context): void
    {
        $amount = $context['amount'] ?? 0;
        $outcome = $amount > 0 ? 'valid' : 'invalid';

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'transaction',
            action: 'validate_online',
            outcome: $outcome,
            metadata: [
                'transaction_id' => $context['transaction_id'] ?? 'unknown',
                'order_id' => $context['order_id'] ?? 'unknown',
                'amount' => $amount,
            ],
        ));
    }

    public function manualReview(string $caseId, string $stepId): void
    {
        // Simulate: always approved in stub
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'transaction',
            action: 'manual_review',
            outcome: 'approved',
            metadata: ['reviewer' => 'fraud_analyst_bot'],
        ));
    }

    public function validateReferral(string $caseId, string $stepId, array $context): void
    {
        // Simulate: referral_code starting with "DUP" is duplicate, "INV" is invalid, rest valid
        $code = $context['referral_code'] ?? '';
        $outcome = match (true) {
            str_starts_with($code, 'DUP') => 'duplicate',
            str_starts_with($code, 'INV') => 'invalid',
            default => 'valid',
        };

        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'transaction',
            action: 'validate_referral',
            outcome: $outcome,
            metadata: [
                'referrer_member_id' => $context['referrer_member_id'] ?? 'unknown',
                'referred_member_id' => $context['referred_member_id'] ?? 'unknown',
                'referral_code' => $code,
            ],
        ));
    }
}
