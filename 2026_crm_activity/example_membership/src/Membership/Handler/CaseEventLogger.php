<?php

declare(strict_types=1);

namespace App\Membership\Handler;

use App\Membership\Event\ActionFinished;
use App\Membership\Event\ActionInitialized;
use App\Membership\Event\ActionPending;
use App\Membership\Event\CaseFinished;
use App\Membership\Event\CaseStarted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Logs all case events for demonstration / audit trail purposes.
 * In production this could write to an event store.
 */
final class CaseEventLogger
{
    /** @var string[] */
    private array $log = [];

    #[AsMessageHandler]
    public function onCaseStarted(CaseStarted $event): void
    {
        $this->log[] = sprintf(
            "[CASE STARTED] case=%s template=%s activity_type=%s",
            $event->caseId,
            $event->templateName,
            $event->activityType,
        );
    }

    #[AsMessageHandler]
    public function onActionInitialized(ActionInitialized $event): void
    {
        $this->log[] = sprintf(
            "  [ACTION INIT] case=%s stage=%s step=%s → %s.%s",
            $event->caseId,
            $event->stageId,
            $event->stepId,
            $event->service,
            $event->action,
        );
    }

    #[AsMessageHandler]
    public function onActionPending(ActionPending $event): void
    {
        $this->log[] = sprintf(
            "  [ACTION PENDING] case=%s step=%s → waiting for %s.%s",
            $event->caseId,
            $event->stepId,
            $event->service,
            $event->action,
        );
    }

    #[AsMessageHandler]
    public function onActionFinished(ActionFinished $event): void
    {
        $next = $event->isTerminal
            ? "(TERMINAL → case_outcome={$event->caseOutcome})"
            : "(next → {$event->nextStepId})";

        $this->log[] = sprintf(
            "  [ACTION DONE] case=%s step=%s outcome=%s %s",
            $event->caseId,
            $event->stepId,
            $event->outcome,
            $next,
        );
    }

    #[AsMessageHandler]
    public function onCaseFinished(CaseFinished $event): void
    {
        $this->log[] = sprintf(
            "[CASE FINISHED] case=%s outcome=%s",
            $event->caseId,
            $event->outcome,
        );
    }

    /** @return string[] */
    public function getLog(): array
    {
        return $this->log;
    }
}
