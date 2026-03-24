<?php

declare(strict_types=1);

namespace App\Membership\Engine;

use App\ExternalService\ServiceDispatcher;
use App\Membership\Model\CaseRepository;
use App\Membership\Model\MembershipCase;
use App\Membership\Model\Outcome;
use App\Membership\Model\Status;
use App\Membership\Model\StepId;
use App\Membership\Template\TemplateRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

final class MembershipEngine
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ServiceDispatcher $serviceDispatcher,
        private readonly MessageBusInterface $eventBus,
        private readonly CaseRepository $caseRepository,
    ) {}

    public function startCase(string $activityType, array $activityData): MembershipCase
    {
        $template = $this->registry->getForActivityType($activityType);
        $case = MembershipCase::start($template, $activityData);

        $this->caseRepository->save($case);
        $this->publishAndDispatch($case);

        return $case;
    }

    public function handleServiceResponse(
        string $caseId,
        string $stepId,
        string $outcome,
        array $metadata = [],
    ): void {
        $case = $this->caseRepository->get($caseId);

        $case->handleStepOutcome(new StepId($stepId), new Outcome($outcome, $metadata));

        $this->caseRepository->save($case);
        $this->publishAndDispatch($case);
    }

    public function getCase(string $caseId): ?MembershipCase
    {
        return $this->caseRepository->findById($caseId);
    }

    private function publishAndDispatch(MembershipCase $case): void
    {
        $events = $case->releaseEvents();

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        if ($case->status() === Status::Pending && $case->currentStepId() !== null) {
            $stepDef = $case->template->findStep($case->currentStepId()->value);

            $lastEvent = end($events);
            if ($lastEvent instanceof \App\Membership\Event\ActionPending
                && $lastEvent->stepId === $case->currentStepId()->value
            ) {
                $this->serviceDispatcher->dispatch(
                    caseId: $case->id->value,
                    stepId: $case->currentStepId()->value,
                    service: $stepDef->service,
                    action: $stepDef->action,
                    context: $case->activityData,
                );
            }
        }
    }
}
