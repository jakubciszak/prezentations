<?php

declare(strict_types=1);

namespace App\Onboarding\Engine;

use App\ExternalService\ServiceDispatcher;
use App\Onboarding\Event\ActionInitialized;
use App\Onboarding\Event\CaseEvent;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\Status;
use App\Onboarding\Template\TemplateRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestrates the onboarding process.
 *
 * - Creates cases from templates
 * - Dispatches steps to external services
 * - Handles responses and advances the case
 * - Publishes domain events via Messenger
 */
final class OnboardingEngine
{
    /** @var array<string, OnboardingCase> */
    private array $cases = [];

    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly ServiceDispatcher $serviceDispatcher,
        private readonly MessageBusInterface $eventBus,
    ) {}

    /**
     * Start a new onboarding case for a given client type.
     */
    public function startCase(string $clientType, array $clientData): OnboardingCase
    {
        $template = $this->registry->getForClientType($clientType);
        $case = OnboardingCase::start($template, $clientData);

        $this->cases[$case->id->value] = $case;

        // Publish events and trigger the first step
        $this->publishAndDispatch($case);

        return $case;
    }

    /**
     * Handle a response from an external service.
     */
    public function handleServiceResponse(
        string $caseId,
        string $stepId,
        string $outcome,
        array $metadata = [],
    ): void {
        $case = $this->cases[$caseId]
            ?? throw new \InvalidArgumentException("Case not found: {$caseId}");

        $case->handleStepOutcome($stepId, new Outcome($outcome, $metadata));

        // Publish events and trigger next step if case is still active
        $this->publishAndDispatch($case);
    }

    public function getCase(string $caseId): ?OnboardingCase
    {
        return $this->cases[$caseId] ?? null;
    }

    private function publishAndDispatch(OnboardingCase $case): void
    {
        $events = $case->releaseEvents();

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        // If the case is still pending, dispatch the current step to external service
        if ($case->status() === Status::Pending && $case->currentStepId() !== null) {
            $stepDef = $case->template->findStep($case->currentStepId());

            // Only dispatch if step was just initialized (last event is ActionPending for this step)
            $lastEvent = end($events);
            if ($lastEvent instanceof \App\Onboarding\Event\ActionPending
                && $lastEvent->stepId === $case->currentStepId()
            ) {
                $this->serviceDispatcher->dispatch(
                    caseId: $case->id->value,
                    stepId: $case->currentStepId(),
                    service: $stepDef->service,
                    action: $stepDef->action,
                    context: $case->clientData,
                );
            }
        }
    }
}
