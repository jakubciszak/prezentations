<?php

declare(strict_types=1);

/**
 * Demo showing different onboarding scenarios based on client data.
 * Same template, different outcomes depending on risk level and KUC status.
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

function runScenario(string $title, string $clientType, array $clientData): void
{
    $loader = new YamlTemplateLoader(__DIR__ . '/config/templates');
    $registry = $loader->loadAll();

    $logger = new CaseEventLogger();
    $engine = null;

    $bus = new MessageBus([
        new HandleMessageMiddleware(new HandlersLocator([
            \App\Onboarding\Event\CaseStarted::class => [fn($e) => $logger->onCaseStarted($e)],
            \App\Onboarding\Event\ActionInitialized::class => [fn($e) => $logger->onActionInitialized($e)],
            \App\Onboarding\Event\ActionPending::class => [fn($e) => $logger->onActionPending($e)],
            \App\Onboarding\Event\ActionFinished::class => [fn($e) => $logger->onActionFinished($e)],
            \App\Onboarding\Event\CaseFinished::class => [fn($e) => $logger->onCaseFinished($e)],
            \App\ExternalService\ExternalServiceResponse::class => [
                function ($r) use (&$engine) {
                    (new ExternalServiceResponseHandler($engine))($r);
                },
            ],
        ])),
    ]);

    $serviceDispatcher = new ServiceDispatcher(
        new KucServiceStub($bus),
        new DocumentServiceStub($bus),
        new ProspectFormServiceStub($bus),
        new RiskCalculationServiceStub($bus),
    );

    $engine = new OnboardingEngine($registry, $serviceDispatcher, $bus);

    echo "\n" . str_repeat('═', 60) . "\n";
    echo "SCENARIO: {$title}\n";
    echo str_repeat('═', 60) . "\n";
    echo "Client: {$clientData['company_name']}\n";
    echo "NIP: {$clientData['nip']} | Revenue: " . number_format($clientData['annual_revenue']) . " PLN\n\n";

    $case = $engine->startCase($clientType, $clientData);

    foreach ($logger->getLog() as $entry) {
        echo $entry . "\n";
    }

    $outcome = $case->caseOutcome()?->value ?? 'none';
    echo "\n→ Case outcome: {$outcome} (status: {$case->status()->value})\n";
}

// Scenario 1: Low risk, clean NIP → basic documents → approved
runScenario(
    'Low risk client – happy path',
    'business_standard',
    [
        'company_name' => 'Mała Firma Sp. z o.o.',
        'nip' => '5261234567',
        'contact_email' => 'jan@malafirma.pl',
        'annual_revenue' => 500_000,
    ],
);

// Scenario 2: Medium risk → extended documents
runScenario(
    'Medium risk client – extended document collection',
    'business_standard',
    [
        'company_name' => 'Średnia Korporacja S.A.',
        'nip' => '7891234567',
        'contact_email' => 'cfo@srednia-korp.pl',
        'annual_revenue' => 5_000_000,
    ],
);

// Scenario 3: Flagged NIP → manual KUC review → then continues
runScenario(
    'Flagged NIP – requires manual KUC review',
    'business_standard',
    [
        'company_name' => 'Podejrzana Sp. z o.o.',
        'nip' => '9961234567',  // starts with 99 → flagged
        'contact_email' => 'info@podejrzana.pl',
        'annual_revenue' => 200_000,
    ],
);

// Scenario 4: NIP not found → rejected
runScenario(
    'NIP not found in registry – rejected',
    'business_standard',
    [
        'company_name' => 'Ghost Company Ltd.',
        'nip' => '0061234567',  // starts with 00 → not_found
        'contact_email' => 'ghost@nowhere.com',
        'annual_revenue' => 100_000,
    ],
);

// Scenario 5: Simplified onboarding for partners
runScenario(
    'Simplified onboarding – pre-verified partner',
    'business_simplified',
    [
        'company_name' => 'Partner Fintech Sp. z o.o.',
        'nip' => '1234567890',
        'contact_email' => 'partner@fintech.pl',
        'partner_referral_code' => 'REF-2026-001',
        'annual_revenue' => 300_000,
    ],
);
