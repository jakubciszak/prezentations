<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Membership\Model\Outcome;
use App\Membership\Model\ServiceAction;
use App\Membership\Model\Step;
use App\Membership\Model\StepId;

final class StepFactory
{
    public static function initialized(
        string $id = 'validate_transaction',
        string $name = 'Validate Transaction',
        string $service = 'transaction',
        string $action = 'validate',
    ): Step {
        return new Step(
            stepId: new StepId($id),
            name: $name,
            serviceAction: new ServiceAction($service, $action),
        );
    }

    public static function pending(
        string $id = 'validate_transaction',
        string $service = 'transaction',
        string $action = 'validate',
    ): Step {
        $step = self::initialized($id, "Step {$id}", $service, $action);
        $step->markPending();

        return $step;
    }

    public static function completed(
        string $id = 'validate_transaction',
        string $outcomeValue = 'valid',
        array $metadata = [],
    ): Step {
        $step = self::pending($id);
        $step->complete(new Outcome($outcomeValue, $metadata));

        return $step;
    }

    public static function failed(
        string $id = 'validate_transaction',
        string $outcomeValue = 'timeout',
        array $metadata = [],
    ): Step {
        $step = self::pending($id);
        $step->fail(new Outcome($outcomeValue, $metadata));

        return $step;
    }
}
