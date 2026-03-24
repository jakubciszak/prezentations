<?php

declare(strict_types=1);

namespace App\MembershipActivity\Application\Adapter;

use App\MembershipActivity\Domain\Activity;
use App\MembershipActivity\Domain\Outcome;

/**
 * Anti-corruption layer: translates an Activity into a facade call
 * on another bounded context and maps the result back to an Outcome.
 *
 * The adapter knows about Activity (our context) AND the external facade.
 * Neither side knows about the other.
 */
interface ActivityAdapter
{
    public function handle(Activity $activity): Outcome;
}
