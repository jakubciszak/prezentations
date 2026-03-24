<?php

declare(strict_types=1);

namespace App\Onboarding\Engine;

use App\ExternalService\ServiceDispatcher;
use App\Onboarding\Model\CaseRepository;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use App\Onboarding\Model\StepId;
use App\Onboarding\Template\TemplateRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

final class OnboardingEngine
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ServiceDispatcher $serviceDispatcher,
        private readonly MessageBusInterface $eventBus,
        private readonly CaseRepository $caseRepository,
    ) {}

    public function startCase(string $clientType, array $clientData): OnboardingCase
    {
        $template = $this->registry->getForClientType($clientType);
        $case = OnboardingCase::start($template, $clientData);

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

    public function getCase(string $caseId): ?OnboardingCase
    {
        return $this->caseRepository->findById($caseId);
    }

    private function publishAndDispatch(OnboardingCase $case): void
    {
        $events = $case->releaseEvents();

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        if ($case->status() === Status::Pending && $case->currentStepId() !== null) {
            $stepDef = $case->template->findStep($case->currentStepId()->value);

            $lastEvent = end($events);
            if ($lastEvent instanceof \App\Onboarding\Event\ActionPending
                && $lastEvent->stepId === $case->currentStepId()->value
            ) {
                $this->serviceDispatcher->dispatch(
                    caseId: $case->id->value,
                    stepId: $case->currentStepId()->value,
                    service: $stepDef->service,
                    action: $stepDef->action,
                    context: $case->clientData,
                );
            }
        }
    }
}
