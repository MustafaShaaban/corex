<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A source that holds records belonging to another screen (spec 112, FR-029): reading it on the
 * Data screen needs that screen's ability as well as the operation's own.
 */
interface RestrictedDataSource
{
    /** The CoreX ability of the screen that owns these records, or null when none does. */
    public function ownerAbility(): ?string;
}
