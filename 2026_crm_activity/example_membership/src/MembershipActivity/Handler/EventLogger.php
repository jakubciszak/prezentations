<?php

declare(strict_types=1);

namespace App\MembershipActivity\Handler;

use App\MembershipActivity\Event\ActivityCompleted;
use App\MembershipActivity\Event\ActivityFailed;
use App\MembershipActivity\Event\ActivityInitialized;
use App\MembershipActivity\Event\MemberOpened;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class EventLogger
{
    /** @var string[] */
    private array $log = [];

    #[AsMessageHandler]
    public function onMemberOpened(MemberOpened $event): void
    {
        $this->log[] = sprintf('[MEMBER OPENED] member=%s name=%s', $event->memberId, $event->name);
    }

    #[AsMessageHandler]
    public function onActivityInitialized(ActivityInitialized $event): void
    {
        $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);
        $this->log[] = sprintf(
            '  [ACTIVITY INITIALIZED] member=%s activity=%s type=%s payload=%s',
            $event->memberId, $event->activityId, $event->activityType, $payload,
        );
    }

    #[AsMessageHandler]
    public function onActivityCompleted(ActivityCompleted $event): void
    {
        $payload = json_encode($event->outcomePayload, JSON_THROW_ON_ERROR);
        $this->log[] = sprintf(
            '  [ACTIVITY COMPLETED] member=%s activity=%s type=%s outcome=%s payload=%s',
            $event->memberId, $event->activityId, $event->activityType, $event->outcomeType, $payload,
        );
    }

    #[AsMessageHandler]
    public function onActivityFailed(ActivityFailed $event): void
    {
        $payload = json_encode($event->payload, JSON_THROW_ON_ERROR);
        $this->log[] = sprintf(
            '  [ACTIVITY FAILED] member=%s activity=%s type=%s reason=%s payload=%s',
            $event->memberId, $event->activityId, $event->activityType, $event->reason, $payload,
        );
    }

    /** @return string[] */
    public function getLog(): array
    {
        return $this->log;
    }
}
