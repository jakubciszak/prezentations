<?php

declare(strict_types=1);

/**
 * CRM Activity Archetype - Business Onboarding Demo
 *
 * This demo shows the full onboarding flow using:
 * - Declarative YAML templates defining stages, steps, and outcome-based transitions
 * - Symfony Messenger as the event bus
 * - External service stubs emitting responses via Messenger
 * - OnboardingCase as aggregate root emitting domain events
 *
 * Usage: php demo.php [business_standard|business_simplified]
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\ExternalService\DocumentServiceStub;
use App\ExternalService\KucServiceStub;
use App\ExternalService\ProspectFormServiceStub;
use App\ExternalService\RiskCalculationServiceStub;
use App\ExternalService\ServiceDispatcher;
use App\Infrastructure\YamlTemplateLoader;
use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Handler\CaseEventLogger;
use App\Onboarding\Handler\ExternalServiceResponseHandler;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

// --- 1. Load templates from YAML ---
$loader = new YamlTemplateLoader(__DIR__ . '/config/templates');
$registry = $loader->loadAll();

$clientType = $argv[1] ?? 'business_standard';
echo "=== CRM Activity Onboarding Demo ===\n";
echo "Template: {$clientType}\n";
echo "Available: " . implode(', ', $registry->availableClientTypes()) . "\n\n";

// --- 2. Wire up Symfony Messenger ---
// We use a synchronous bus for the demo - in production you'd use async transports

$logger = new CaseEventLogger();

// The engine needs the bus, but the bus also needs the engine (via the response handler).
// We solve this with lazy initialization.
$engine = null;

$bus = new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([
        // Domain events → logger (audit trail)
        \App\Onboarding\Event\CaseStarted::class => [fn($e) => $logger->onCaseStarted($e)],
        \App\Onboarding\Event\ActionInitialized::class => [fn($e) => $logger->onActionInitialized($e)],
        \App\Onboarding\Event\ActionPending::class => [fn($e) => $logger->onActionPending($e)],
        \App\Onboarding\Event\ActionFinished::class => [fn($e) => $logger->onActionFinished($e)],
        \App\Onboarding\Event\CaseFinished::class => [fn($e) => $logger->onCaseFinished($e)],

        // External service responses → engine
        \App\ExternalService\ExternalServiceResponse::class => [
            function ($response) use (&$engine) {
                $handler = new ExternalServiceResponseHandler($engine);
                $handler($response);
            },
        ],
    ])),
]);

// --- 3. Create external service stubs ---
$kuc = new KucServiceStub($bus);
$documents = new DocumentServiceStub($bus);
$prospectForm = new ProspectFormServiceStub($bus);
$riskCalculation = new RiskCalculationServiceStub($bus);

$serviceDispatcher = new ServiceDispatcher($kuc, $documents, $prospectForm, $riskCalculation);

// --- 4. Create the engine ---
$engine = new OnboardingEngine($registry, $serviceDispatcher, $bus);

// --- 5. Run the onboarding ---
$clientData = [
    'company_name' => 'Acme Fintech Sp. z o.o.',
    'nip' => '5261234567',
    'contact_email' => 'onboarding@acme-fintech.pl',
    'annual_revenue' => 500_000, // low risk
];

echo "Client: {$clientData['company_name']} (NIP: {$clientData['nip']})\n";
echo "Annual revenue: " . number_format($clientData['annual_revenue']) . " PLN\n";
echo str_repeat('─', 60) . "\n\n";

$case = $engine->startCase($clientType, $clientData);

// --- 6. Print the event log ---
echo "Event Log:\n";
echo str_repeat('─', 60) . "\n";
foreach ($logger->getLog() as $entry) {
    echo $entry . "\n";
}

echo "\n" . str_repeat('─', 60) . "\n";
echo "Case ID: {$case->id}\n";
echo "Final status: {$case->status()->value}\n";
echo "Case outcome: {$case->caseOutcome()}\n";

// --- 7. Print stage summary ---
echo "\nStage Summary:\n";
foreach ($case->stages() as $stage) {
    echo "  [{$stage->status()->value}] {$stage->name}\n";
    foreach ($stage->steps() as $step) {
        $outcomeStr = $step->outcome() ? " → {$step->outcome()->value}" : '';
        echo "    [{$step->status()->value}] {$step->name}{$outcomeStr}\n";
    }
}
