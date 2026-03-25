<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\MembershipActivity\Domain\MemberAccount;

final class SharedState
{
    private static ?self $instance = null;

    public ?MemberAccount $currentAccount = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
