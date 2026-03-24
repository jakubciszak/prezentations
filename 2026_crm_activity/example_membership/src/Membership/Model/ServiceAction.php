<?php

declare(strict_types=1);

namespace App\Membership\Model;

final readonly class ServiceAction
{
    public function __construct(
        public string $service,
        public string $action,
    ) {}

    public function toString(): string
    {
        return "{$this->service}.{$this->action}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
