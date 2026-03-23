<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

use App\Onboarding\Event\ActionFinished;
use App\Onboarding\Event\ActionInitialized;
use App\Onboarding\Event\ActionPending;
use App\Onboarding\Event\CaseEvent;
use App\Onboarding\Event\CaseFinished;
use App\Onboarding\Event\CaseStarted;
use App\Onboarding\Template\OnboardingTemplate;
use App\Onboarding\Template\StepDefinition;

final class OnboardingCase
{
    private Status $status;
    private ?string $currentStageId = null;
    private ?string $currentStepId = null;
    private ?string $caseOutcome = null;

    /** @var Stage[] */
    private array $stages = [];

    /** @var CaseEvent[] */
    private array $recordedEvents = [];

    private function __construct(
        public readonly CaseId $id,
        public readonly OnboardingTemplate $template,
        public readonly array $clientData,
    ) {
        $this->status = Status::Initialized;
    }

    /**
     * Start a new onboarding case from a template.
     * Builds all stages and steps upfront from the declarative definition.
     */
    public static function start(OnboardingTemplate $template, array $clientData): self
    {
        $case = new self(CaseId::generate(), $template, $clientData);
        $case->buildFromTemplate();

        $case->recordEvent(new CaseStarted(
            caseId: $case->id->value,
            templateName: $template->name,
            clientType: $template->clientType,
            clientData: $clientData,
        ));

        // Automatically initialize the first step
        $case->initializeFirstStep();

        return $case;
    }

    /**
     * Handle outcome from an external service.
     * The engine calls this when a service responds.
     */
    public function handleStepOutcome(string $stepId, Outcome $outcome): void
    {
        $stage = $this->findStageForStep($stepId);
        if ($stage === null) {
            throw new \InvalidArgumentException("Step {$stepId} not found in any stage");
        }

        $step = $stage->getStep($stepId);
        $step->complete($outcome);

        // Resolve transition from template
        $stepDef = $this->template->findStep($stepId);
        $transition = $stepDef->outcomes[$outcome->value] ?? null;

        if ($transition === null) {
            throw new \RuntimeException(
                "No transition defined for outcome '{$outcome->value}' on step '{$stepId}'"
            );
        }

        $isTerminal = $transition['terminal'] ?? false;
        $caseOutcome = $transition['case_outcome'] ?? null;
        $nextStepId = $transition['next_step'] ?? null;

        $this->recordEvent(new ActionFinished(
            caseId: $this->id->value,
            stageId: $stage->stageId,
            stepId: $stepId,
            outcome: $outcome->value,
            isTerminal: $isTerminal,
            caseOutcome: $caseOutcome,
            nextStepId: $nextStepId,
            metadata: $outcome->metadata,
        ));

        if ($isTerminal) {
            $this->finish($caseOutcome ?? $outcome->value);
            return;
        }

        if ($nextStepId !== null) {
            $this->advanceTo($nextStepId);
        }
    }

    /** @return CaseEvent[] */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function currentStepId(): ?string
    {
        return $this->currentStepId;
    }

    public function currentStageId(): ?string
    {
        return $this->currentStageId;
    }

    public function caseOutcome(): ?string
    {
        return $this->caseOutcome;
    }

    /** @return Stage[] */
    public function stages(): array
    {
        return $this->stages;
    }

    // --- Private methods ---

    private function buildFromTemplate(): void
    {
        foreach ($this->template->stages as $stageDef) {
            $stage = new Stage($stageDef->id, $stageDef->name);

            foreach ($stageDef->steps as $stepDef) {
                $stage->addStep(new Step(
                    stepId: $stepDef->id,
                    name: $stepDef->name,
                    service: $stepDef->service,
                    action: $stepDef->action,
                ));
            }

            $this->stages[$stageDef->id] = $stage;
        }
    }

    private function initializeFirstStep(): void
    {
        $firstStage = $this->template->stages[0] ?? null;
        $firstStep = $firstStage?->steps[0] ?? null;

        if ($firstStage === null || $firstStep === null) {
            throw new \RuntimeException('Template has no stages or steps defined');
        }

        $this->advanceTo($firstStep->id);
    }

    private function advanceTo(string $stepId): void
    {
        $stage = $this->findStageForStep($stepId);
        if ($stage === null) {
            // Step might be in a different stage - search template
            $stepDef = $this->template->findStep($stepId);
            $stageDef = $this->template->findStageForStep($stepId);
            $stage = $this->stages[$stageDef->id];
        }

        // If we're moving to a new stage, mark the old one complete
        if ($this->currentStageId !== null && $this->currentStageId !== $stage->stageId) {
            $this->stages[$this->currentStageId]->markCompleted();
        }

        $this->currentStageId = $stage->stageId;
        $this->currentStepId = $stepId;
        $this->status = Status::Pending;

        $step = $stage->getStep($stepId);

        $this->recordEvent(new ActionInitialized(
            caseId: $this->id->value,
            stageId: $stage->stageId,
            stepId: $stepId,
            service: $step->service,
            action: $step->action,
        ));

        $stage->startStep($stepId);

        $this->recordEvent(new ActionPending(
            caseId: $this->id->value,
            stageId: $stage->stageId,
            stepId: $stepId,
            service: $step->service,
            action: $step->action,
        ));
    }

    private function finish(string $outcome): void
    {
        $this->status = Status::Completed;
        $this->caseOutcome = $outcome;

        if ($this->currentStageId !== null) {
            $this->stages[$this->currentStageId]->markCompleted();
        }

        $this->recordEvent(new CaseFinished(
            caseId: $this->id->value,
            outcome: $outcome,
        ));
    }

    private function findStageForStep(string $stepId): ?Stage
    {
        foreach ($this->stages as $stage) {
            if ($stage->getStep($stepId) !== null) {
                return $stage;
            }
        }
        return null;
    }

    private function recordEvent(CaseEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
