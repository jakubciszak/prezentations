<?php

declare(strict_types=1);

namespace App\ExternalService;

/**
 * Routes step execution to the appropriate external service stub.
 * In production, this could dispatch async messages to real services.
 */
final readonly class ServiceDispatcher
{
    public function __construct(
        private KucServiceStub $kuc,
        private DocumentServiceStub $documents,
        private ProspectFormServiceStub $prospectForm,
        private RiskCalculationServiceStub $riskCalculation,
    ) {}

    public function dispatch(
        string $caseId,
        string $stepId,
        string $service,
        string $action,
        array $context = [],
    ): void {
        match ($service) {
            'kuc' => match ($action) {
                'check_registry' => $this->kuc->checkRegistry($caseId, $stepId, $context['nip'] ?? ''),
                'manual_review' => $this->kuc->manualReview($caseId, $stepId),
            },
            'documents' => match ($action) {
                'request_basic' => $this->documents->requestBasic($caseId, $stepId),
                'request_extended' => $this->documents->requestExtended($caseId, $stepId),
            },
            'prospect_form' => match ($action) {
                'collect' => $this->prospectForm->collect($caseId, $stepId, $context),
            },
            'risk_calculation' => match ($action) {
                'calculate' => $this->riskCalculation->calculate($caseId, $stepId, $context),
                'quick_check' => $this->riskCalculation->quickCheck($caseId, $stepId),
                'final_check' => $this->riskCalculation->finalCheck($caseId, $stepId),
                'auto_approve' => $this->riskCalculation->autoApprove($caseId, $stepId),
            },
            default => throw new \InvalidArgumentException("Unknown service: {$service}"),
        };
    }
}
