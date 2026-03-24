<?php

declare(strict_types=1);

namespace Features\Bootstrap;

use App\Membership\Model\MemberAccount;
use App\Membership\Model\Reward\RewardCatalog;

final class SharedState
{
    private static ?self $instance = null;

    public ?MemberAccount $currentAccount = null;
    public ?RewardCatalog $rewardCatalog = null;

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
