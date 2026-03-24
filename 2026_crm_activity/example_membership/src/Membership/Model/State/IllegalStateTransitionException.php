<?php

declare(strict_types=1);

namespace App\Membership\Model\State;

use App\Membership\Model\Status;

final class IllegalStateTransitionException extends \DomainException
{
    public static function create(Status $from, string $action): self
    {
        return new self("Cannot '{$action}' step in state '{$from->value}'");
    }
}
