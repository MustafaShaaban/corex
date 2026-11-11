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

    /**
     * The keys of teams that were removed. A removed team's submissions keep its key, so no new
     * team may take it: that would hand the new team the old one's submissions.
     *
     * @return list<string>
     */
    public function retiredKeys(): array;

    public function retire(string $key): void;
}
