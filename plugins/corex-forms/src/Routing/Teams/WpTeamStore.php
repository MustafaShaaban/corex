<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing\Teams;

defined('ABSPATH') || exit;

/**
 * Teams in one site option (DECISIONS #472): a site has a handful, read whole whenever routing or
 * the inbox asks, and written whole when one changes.
 */
final class WpTeamStore implements TeamStore
{
    public const OPTION = 'corex_flow_teams';

    public function all(): array
    {
        $stored = get_option(self::OPTION, []);
        $teams = [];
        foreach (is_array($stored) ? $stored : [] as $entry) {
            $team = is_array($entry) ? Team::fromArray($entry) : null;
            if ($team !== null) {
                $teams[] = $team;
            }
        }

        return $teams;
    }

    public function replace(array $teams): void
    {
        update_option(
            self::OPTION,
            array_map(static fn (Team $team): array => $team->toArray(), array_values($teams)),
            true,
        );
    }
}
