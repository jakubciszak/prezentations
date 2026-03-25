<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;

/**
 * Anti-corruption layer: translates an Activity into facade calls
 * on other bounded contexts (calculation + wallet) and returns an Outcome.
 *
 * The adapter knows about Activity (our context), the external facade,
 * AND the wallet facade. Neither external context knows about the other.
 */
interface ActivityAdapter
{
    public function handle(Activity $activity, string $memberId): Outcome;
}
