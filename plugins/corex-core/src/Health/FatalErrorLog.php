<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * The PHP fatal errors CoreX has seen on this site (spec 118, OVR-D3): for each one its time, its
 * PHP error type and the plugin or theme its file belongs to. Nothing else: no message, no file
 * path, nothing about the visitor. Kept 30 days, at most 50 entries, in one option that is not
 * autoloaded. Read by the Overview's Site health tile.
 */
class FatalErrorLog
{
    public const OPTION = 'corex_php_fatal_errors';

    public const KEEP_DAYS = 30;

    private const MOST = 50;

    /**
     * @param array{time:int,type:int,kind:string,slug:string} $entry
     */
    public function record(array $entry): void
    {
        $since   = $entry['time'] - self::KEEP_DAYS * DAY_IN_SECONDS;
        $entries = array_values(array_filter($this->all(), static fn (array $e): bool => $e['time'] >= $since));
        $entries[] = $entry;

        update_option(self::OPTION, array_slice($entries, -self::MOST), false);
    }

    /**
     * The newest entry at or after a moment, or null.
     *
     * @return array{time:int,type:int,kind:string,slug:string}|null
     */
    public function latestSince(int $since): ?array
    {
        $latest = null;
        foreach ($this->all() as $entry) {
            if ($entry['time'] >= $since && ($latest === null || $entry['time'] > $latest['time'])) {
                $latest = $entry;
            }
        }

        return $latest;
    }

    /** @return list<array{time:int,type:int,kind:string,slug:string}> */
    private function all(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            return [];
        }

        $entries = [];
        foreach ($stored as $entry) {
            if (is_array($entry) && isset($entry['time'], $entry['type'], $entry['kind'], $entry['slug'])) {
                $entries[] = [
                    'time' => (int) $entry['time'],
                    'type' => (int) $entry['type'],
                    'kind' => (string) $entry['kind'],
                    'slug' => (string) $entry['slug'],
                ];
            }
        }

        return $entries;
    }
}
