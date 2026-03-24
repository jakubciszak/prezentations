<?php

declare(strict_types=1);

namespace App\Membership\Handler;

use App\Membership\Event\MemberOpened;
use App\Membership\Event\PointsActivated;
use App\Membership\Event\PointsEarned;
use App\Membership\Event\PointsPending;
use App\Membership\Event\PointsSpent;
use App\Membership\Event\RewardRedeemed;
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
            '  [POINTS EARNED] member=%s activity=%s +%d pts (balance=%d) %s',
            $event->memberId, $event->activityId, $event->points, $event->balance, $event->description,
        );
    }

    #[AsMessageHandler]
    public function onPointsPending(PointsPending $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS PENDING] member=%s activity=%s +%d pts (awaiting=%s) %s',
            $event->memberId, $event->activityId, $event->points, $event->awaitingReference, $event->description,
        );
    }

    #[AsMessageHandler]
    public function onPointsActivated(PointsActivated $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS ACTIVATED] member=%s activity=%s +%d pts (ref=%s, active_balance=%d)',
            $event->memberId, $event->activityId, $event->points, $event->reference, $event->activeBalance,
        );
    }

    #[AsMessageHandler]
    public function onPointsSpent(PointsSpent $event): void
    {
        $this->log[] = sprintf(
            '  [POINTS SPENT] member=%s activity=%s -%d pts (balance=%d) %s',
            $event->memberId, $event->activityId, $event->points, $event->balance, $event->description,
        );
    }

    #[AsMessageHandler]
    public function onRewardRedeemed(RewardRedeemed $event): void
    {
        $this->log[] = sprintf(
            '  [REWARD REDEEMED] member=%s reward=%s (%s) -%d pts',
            $event->memberId, $event->rewardId, $event->rewardName, $event->pointsSpent,
        );
    }

    /** @return string[] */
    public function getLog(): array
    {
        return $this->log;
    }
}
