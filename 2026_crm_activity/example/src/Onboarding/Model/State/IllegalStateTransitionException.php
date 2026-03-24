<?php

declare(strict_types=1);

namespace App\Onboarding\Model\State;

use App\Onboarding\Model\Status;

final class IllegalStateTransitionException extends \DomainException
{
    public static function create(Status $from, string $action): self
    {
        return new self("Cannot '{$action}' step in state '{$from->value}'");
    }
}
