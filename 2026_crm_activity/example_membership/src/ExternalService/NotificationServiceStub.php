<?php

declare(strict_types=1);

namespace App\ExternalService;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for the notification service.
 * In production this would send push notifications, emails, or SMS.
 */
final readonly class NotificationServiceStub
{
    public function __construct(private MessageBusInterface $bus) {}

    public function sendReferralNotification(string $caseId, string $stepId, array $context): void
    {
        $this->bus->dispatch(new ExternalServiceResponse(
            caseId: $caseId,
            stepId: $stepId,
            service: 'notification',
            action: 'send_referral_notification',
            outcome: 'notified',
            metadata: [
                'channels' => ['push', 'email'],
                'referrer_member_id' => $context['referrer_member_id'] ?? 'unknown',
                'referred_member_id' => $context['referred_member_id'] ?? 'unknown',
            ],
        ));
    }
}
