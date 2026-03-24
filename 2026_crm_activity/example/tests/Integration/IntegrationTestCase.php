<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Onboarding\Engine\OnboardingEngine;
use App\Onboarding\Handler\CaseEventLogger;
use App\Onboarding\Model\CaseOutcome;
use App\Onboarding\Model\CaseRepository;
use App\Onboarding\Model\OnboardingCase;
use App\Onboarding\Model\Status;
use Munus\Collection\Stream;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    protected OnboardingEngine $engine;
    protected CaseEventLogger $eventLogger;
    protected CaseRepository $caseRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->engine = $container->get(OnboardingEngine::class);
        $this->eventLogger = $container->get(CaseEventLogger::class);
        $this->caseRepository = $container->get(CaseRepository::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    // ========== given ==========

    protected function givenCaseStarted(string $clientType, array $clientData): OnboardingCase
    {
        return $this->engine->startCase($clientType, $clientData);
    }

    // ========== then: case ==========

    protected function thenCaseIsCompletedWith(OnboardingCase $case, CaseOutcome $expectedOutcome): void
    {
        self::assertSame(Status::Completed, $case->status());
        self::assertSame($expectedOutcome, $case->caseOutcome());
    }

    protected function thenCaseStatusIs(OnboardingCase $case, Status $expected): void
    {
        self::assertSame($expected, $case->status());
    }

    // ========== then: events ==========

    /** @return string[] */
    protected function thenStepsInitializedInOrder(): array
    {
        return Stream::ofAll($this->eventLogger->getLog())
            ->filter(fn(string $line) => str_contains($line, '[ACTION INIT]'))
            ->map(function (string $line): string {
                preg_match('/step=(\S+)/', $line, $m);
                return $m[1];
            })
            ->toArray();
    }

    protected function thenEventLogContains(string $fragment): void
    {
        $found = Stream::ofAll($this->eventLogger->getLog())
            ->find(fn(string $line) => str_contains($line, $fragment));

        self::assertTrue($found->isPresent(), "Expected event log to contain '{$fragment}'");
    }

    protected function thenEventLogStartsWithCaseStarted(): void
    {
        $log = $this->eventLogger->getLog();
        self::assertNotEmpty($log);
        self::assertStringContainsString('[CASE STARTED]', $log[0]);
    }

    protected function thenEventLogEndsWithCaseFinished(): void
    {
        $log = $this->eventLogger->getLog();
        self::assertNotEmpty($log);
        self::assertStringContainsString('[CASE FINISHED]', end($log));
    }

    // ========== then: stages ==========

    protected function thenAllStagesAreCompleted(OnboardingCase $case): void
    {
        $case->stages()->forEach(function ($stage) {
            self::assertSame(
                Status::Completed,
                $stage->status(),
                "Stage '{$stage->name}' should be completed but is {$stage->status()->value}",
            );
        });
    }

    /** @return array<string, Status> */
    protected function thenStageStatuses(OnboardingCase $case): array
    {
        $statuses = [];
        $case->stages()->forEach(function ($stage) use (&$statuses) {
            $statuses[$stage->name] = $stage->status();
        });

        return $statuses;
    }
}
