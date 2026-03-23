<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Onboarding\Model\Outcome;
use App\Onboarding\Model\ServiceAction;
use App\Onboarding\Model\Step;
use App\Onboarding\Model\StepId;

final class StepFactory
{
    public static function initialized(
        string $id = 'check_kuc',
        string $name = 'KUC Registry Check',
        string $service = 'kuc',
        string $action = 'check_registry',
    ): Step {
        return new Step(
            stepId: new StepId($id),
            name: $name,
            serviceAction: new ServiceAction($service, $action),
        );
    }

    public static function pending(
        string $id = 'check_kuc',
        string $service = 'kuc',
        string $action = 'check_registry',
    ): Step {
        $step = self::initialized($id, "Step {$id}", $service, $action);
        $step->markPending();

        return $step;
    }

    public static function completed(
        string $id = 'check_kuc',
        string $outcomeValue = 'clean',
        array $metadata = [],
    ): Step {
        $step = self::pending($id);
        $step->complete(new Outcome($outcomeValue, $metadata));

        return $step;
    }

    public static function failed(
        string $id = 'check_kuc',
        string $outcomeValue = 'timeout',
        array $metadata = [],
    ): Step {
        $step = self::pending($id);
        $step->fail(new Outcome($outcomeValue, $metadata));

        return $step;
    }
}
