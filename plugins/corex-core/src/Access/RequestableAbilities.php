<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * What an access request may ask for (spec 112, FR-001).
 *
 * An ability that is locked or critical controls the whole site: who has access, how the site
 * runs, what code it runs. An administrator grants those deliberately, through the role matrix,
 * or not at all; a request never does. Nor does a request for an ability that implies one, or for
 * an area that holds one.
 *
 * One answer for every place a request is made or offered: the service refuses what this refuses,
 * and the denied page offers no form for it.
 */
final class RequestableAbilities
{
    public function __construct(private readonly CorexAbilityCatalog $catalog)
    {
    }

    /** Whether a request may ask for this registered ability. */
    public function isRequestable(string $abilityKey): bool
    {
        foreach ($this->catalog->expanded([$abilityKey]) as $key) {
            $ability = $this->catalog->find($key);

            if ($ability === null || $ability->locked || $ability->risk === CorexAbility::RISK_CRITICAL) {
                return false;
            }
        }

        return true;
    }

    /** Whether a request may ask for every ability of this area. */
    public function isAreaRequestable(string $areaKey): bool
    {
        foreach ($this->catalog->grouped()[$areaKey] ?? [] as $ability) {
            if (! $this->isRequestable($ability->key)) {
                return false;
            }
        }

        return true;
    }
}
