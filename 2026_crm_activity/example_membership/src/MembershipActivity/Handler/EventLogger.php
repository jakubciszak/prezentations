<?php

declare(strict_types=1);

namespace App\MembershipActivity\Handler;

use App\MembershipActivity\Event\MemberOpened;
use App\MembershipActivity\Event\PointsActivated;
use App\MembershipActivity\Event\PointsEarned;
use App\MembershipActivity\Event\PointsPending;
use App\MembershipActivity\Event\PointsSpent;
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
    public function onPointsEarned(PointsEarned $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS EARNED] member=%s +%d pts (balance=%d) %s',
            $event->memberId, $event->points, $event->balance, $event->description,
        );
    }

    #[AsMessageHandler]
    public function onPointsPending(PointsPending $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS PENDING] member=%s +%d pts (awaiting=%s) %s',
            $event->memberId, $event->points, $event->awaitingReference, $event->description,
        );
    }

    #[AsMessageHandler]
    public function onPointsActivated(PointsActivated $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS ACTIVATED] member=%s +%d pts (ref=%s, active_balance=%d)',
            $event->memberId, $event->points, $event->reference, $event->activeBalance,
        );
    }

    #[AsMessageHandler]
    public function onPointsSpent(PointsSpent $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS SPENT] member=%s -%d pts (balance=%d) %s',
            $event->memberId, $event->points, $event->balance, $event->description,
        );
    }

    /** @return string[] */
    public function getLog(): array
    {
        return $this->log;
    }
}
