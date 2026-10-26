<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing\Teams;

defined('ABSPATH') || exit;

/**
 * Where the site's teams are kept.
 */
interface TeamStore
{
    /** @return list<Team> */
    public function all(): array;

    /** @param list<Team> $teams */
    public function replace(array $teams): void;
}
