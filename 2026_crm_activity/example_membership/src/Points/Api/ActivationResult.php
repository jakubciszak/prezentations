<?php

declare(strict_types=1);

namespace App\Points\Api;

/**
 * Confirmation that points for a given reference may be activated.
 */
final readonly class ActivationResult
{
    public function __construct(
        public string $reference,
    ) {}
}
