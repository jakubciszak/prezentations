<?php

declare(strict_types=1);

namespace App\Membership\Model;

use App\Membership\Engine\TransitionResolver;
use App\Membership\Event\ActionFinished;
use App\Membership\Event\ActionInitialized;
use App\Membership\Event\ActionPending;
use App\Membership\Event\CaseEvent;
use App\Membership\Event\CaseFinished;
use App\Membership\Event\CaseStarted;
use App\Membership\Template\ActivityTemplate;
use Munus\Collection\Stream;
use Munus\Control\Option;

final class MembershipCase
{
    private Status $status;
    private ?StageId $currentStageId = null;
    private ?StepId $currentStepId = null;
    private ?CaseOutcome $caseOutcome = null;

    /** @var array<string, Stage> */
    private array $stages = [];

    /** @var CaseEvent[] */
    private array $recordedEvents = [];

    private TransitionResolver $transitionResolver;

    private function __construct(
        public readonly CaseId $id,
        public readonly ActivityTemplate $template,
        public readonly array $activityData,
    ) {
        $this->status = Status::Initialized;
        $this->transitionResolver = new TransitionResolver();
    }

    /**
     * Start a new membership activity case from a template.
     * Builds all stages and steps upfront from the declarative definition.
     */
    public static function start(ActivityTemplate $template, array $activityData): self
    {
        $case = new self(CaseId::generate(), $template, $activityData);
        $case->buildFromTemplate();

        $case->recordEvent(new CaseStarted(
            caseId: $case->id->value,
            templateName: $template->name,
            activityType: $template->activityType,
            activityData: $activityData,
        ));

        // Automatically initialize the first step
        $case->initializeFirstStep();

        return $case;
    }

    /**
     * Handle outcome from an external service.
     * The engine calls this when a service responds.
     */
    public function handleStepOutcome(StepId $stepId, Outcome $outcome): void
    {
        $stage = $this->findStageForStep($stepId)
            ->getOrElseThrow(new \InvalidArgumentException("Step {$stepId} not found in any stage"));

        $step = $stage->getStep($stepId)->get();
        $step->complete($outcome);

        // Resolve transition via rule-engine
        $stepDef = $this->template->findStep($stepId->value);
        $transition = $this->transitionResolver->resolve($stepDef, $outcome)
            ->getOrElseThrow(new \RuntimeException(
                "No transition defined for outcome '{$outcome->value}' on step '{$stepId}'"
            ));

        $this->recordEvent(new ActionFinished(
            caseId: $this->id->value,
            stageId: $stage->stageId->value,
            stepId: $stepId->value,
            outcome: $outcome->value,
            isTerminal: $transition->isTerminal,
            caseOutcome: $transition->caseOutcome?->value,
            nextStepId: $transition->nextStepId?->value,
            metadata: $outcome->metadata,
        ));

        if ($transition->isTerminal) {
            $this->finish($transition->caseOutcome);
            return;
        }

        if ($transition->nextStepId !== null) {
            $this->advanceTo($transition->nextStepId);
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

    public function currentStepId(): ?StepId
    {
        return $this->currentStepId;
    }

    public function currentStageId(): ?StageId
    {
        return $this->currentStageId;
    }

    public function caseOutcome(): ?CaseOutcome
    {
        return $this->caseOutcome;
    }

    /**
     * @return Stream<Stage>
     */
    public function stages(): Stream
    {
        return Stream::ofAll(array_values($this->stages));
    }

    // --- Private methods ---

    private function buildFromTemplate(): void
    {
        Stream::ofAll($this->template->stages)->forEach(function ($stageDef) {
            $stageId = new StageId($stageDef->id);
            $stage = new Stage($stageId, $stageDef->name);

            Stream::ofAll($stageDef->steps)->forEach(function ($stepDef) use ($stage) {
                $stage->addStep(new Step(
                    stepId: new StepId($stepDef->id),
                    name: $stepDef->name,
                    serviceAction: new ServiceAction($stepDef->service, $stepDef->action),
                ));
            });

            $this->stages[$stageId->value] = $stage;
        });
    }

    private function initializeFirstStep(): void
    {
        $firstStage = $this->template->stages[0] ?? null;
        $firstStep = $firstStage?->steps[0] ?? null;

        if ($firstStage === null || $firstStep === null) {
            throw new \RuntimeException('Template has no stages or steps defined');
        }

        $this->advanceTo(new StepId($firstStep->id));
    }

    private function advanceTo(StepId $stepId): void
    {
        $stage = $this->findStageForStep($stepId)->getOrElse(null);

        if ($stage === null) {
            // Step might be in a different stage - search template
            $stageDef = $this->template->findStageForStep($stepId->value);
            $stage = $this->stages[$stageDef->id];
        }

        $stageId = $stage->stageId;

        // If we're moving to a new stage, mark the old one complete
        if ($this->currentStageId !== null && !$this->currentStageId->equals($stageId)) {
            $this->stages[$this->currentStageId->value]->markCompleted();
        }

        $this->currentStageId = $stageId;
        $this->currentStepId = $stepId;
        $this->status = Status::Pending;

        $step = $stage->getStep($stepId)->get();

        $this->recordEvent(new ActionInitialized(
            caseId: $this->id->value,
            stageId: $stageId->value,
            stepId: $stepId->value,
            service: $step->serviceAction->service,
            action: $step->serviceAction->action,
        ));

        $stage->startStep($stepId);

        $this->recordEvent(new ActionPending(
            caseId: $this->id->value,
            stageId: $stageId->value,
            stepId: $stepId->value,
            service: $step->serviceAction->service,
            action: $step->serviceAction->action,
        ));
    }

    private function finish(?CaseOutcome $outcome): void
    {
        $this->status = Status::Completed;
        $this->caseOutcome = $outcome;

        if ($this->currentStageId !== null) {
            $this->stages[$this->currentStageId->value]->markCompleted();
        }

        $this->recordEvent(new CaseFinished(
            caseId: $this->id->value,
            outcome: $outcome?->value ?? 'unknown',
        ));
    }

    /**
     * @return Option<Stage>
     */
    private function findStageForStep(StepId $stepId): Option
    {
        return Stream::ofAll(array_values($this->stages))
            ->find(fn(Stage $stage) => $stage->hasStep($stepId));
    }

    private function recordEvent(CaseEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
