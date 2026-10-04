<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Persists the CoreX operations mode and a bounded change audit log (spec 065). The mode lives in a
 * prefixed, autoload-off option; the audit log is a short capped list of {time, user, from, to}. When
 * no mode has been declared it truthfully falls back to `wp_get_environment_type()`, so the Overview
 * badge shows a real value from the first load. This is the only boundary that writes the mode.
 *
 * Since spec 101 the log also holds the preview link's events — created, regenerated, revoked — as
 * rows of their own kind: {time, user, event}. They are part of the same story an operator reads
 * here ("who opened the site to the client, and when"), and they never hold the link itself.
 */
final class OperationsModeStore
{
    private const OPTION = 'corex_operations_mode';
    private const LOG    = 'corex_operations_mode_log';
    private const MAX_LOG = 20;

    public const EVENT_PREVIEW_CREATED     = 'preview_link_created';
    public const EVENT_PREVIEW_REGENERATED = 'preview_link_regenerated';
    public const EVENT_PREVIEW_REVOKED     = 'preview_link_revoked';

    private const EVENTS = [
        self::EVENT_PREVIEW_CREATED,
        self::EVENT_PREVIEW_REGENERATED,
        self::EVENT_PREVIEW_REVOKED,
    ];

    public function __construct(private readonly OperationsMode $modes)
    {
    }

    /** The current declared mode, defaulting to the real WordPress environment type when unset. */
    public function current(): string
    {
        $stored = (string) get_option(self::OPTION, '');
        if ($stored !== '' && $this->modes->isValid($stored)) {
            return $stored;
        }

        $env = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : '';

        return $this->modes->normalize($env);
    }

    /** Whether the operator has explicitly declared a mode (vs. inheriting the environment default). */
    public function isDeclared(): bool
    {
        $stored = (string) get_option(self::OPTION, '');

        return $stored !== '' && $this->modes->isValid($stored);
    }

    /**
     * Persist a new mode and append an audit entry. Returns the normalised mode actually stored.
     * Caller is responsible for the capability + nonce gate.
     *
     * **A no-change is not a change.** Re-applying the mode already in force writes nothing and
     * logs nothing: the history exists to answer "when did this site go live, and who did it", and
     * a log containing `development → development` rows stops being able to.
     *
     * The guard lives here rather than in the controller because the invariant belongs to the log.
     * A controller-side check would protect the one caller that goes through it and leave every
     * other caller — a future CLI command, a migration, a test — free to write a fiction.
     *
     * Declaring the mode the site has merely *inherited* is a real change and is still recorded:
     * it moves the site from following `wp_get_environment_type()` to stating its own position,
     * which is exactly the transition an operator later wants to find in this log. Hence the
     * `isDeclared()` term rather than a bare `$from === $to`.
     */
    public function set(string $mode, int $userId): string
    {
        $from = $this->current();
        $to   = $this->modes->normalize($mode);

        if ($from === $to && $this->isDeclared()) {
            return $to;
        }

        update_option(self::OPTION, $to, false);
        $this->appendLog($from, $to, $userId);

        return $to;
    }

    /**
     * Record something that happened which is not a change of mode. Only the events named above
     * are accepted: the log is a closed vocabulary the screen can render, not a place a caller
     * can write free text into.
     */
    public function record(string $event, int $userId): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        $this->append(['time' => time(), 'user' => $userId, 'event' => $event]);
    }

    /**
     * Everything in the log, newest first: mode changes and events together. A mode change has an
     * empty `event`; an event has empty `from` and `to`.
     *
     * @return list<array{time:int,user:int,from:string,to:string,event:string}>
     */
    public function timeline(int $limit = self::MAX_LOG): array
    {
        $log = get_option(self::LOG, []);
        if (! is_array($log)) {
            return [];
        }

        $entries = [];
        foreach ($log as $entry) {
            if (! is_array($entry) || (! isset($entry['to']) && ! isset($entry['event']))) {
                continue;
            }
            $entries[] = [
                'time'  => (int) ($entry['time'] ?? 0),
                'user'  => (int) ($entry['user'] ?? 0),
                'from'  => (string) ($entry['from'] ?? ''),
                'to'    => (string) ($entry['to'] ?? ''),
                'event' => (string) ($entry['event'] ?? ''),
            ];
        }

        return array_slice(array_reverse($entries), 0, max(1, $limit));
    }

    /**
     * The mode changes, newest first. Events are not mode changes and are left out, so the callers
     * that ask "when did this site change mode" keep getting exactly that.
     *
     * @return list<array{time:int,user:int,from:string,to:string}>
     */
    public function history(int $limit = self::MAX_LOG): array
    {
        $log = get_option(self::LOG, []);
        if (! is_array($log)) {
            return [];
        }

        $entries = [];
        foreach ($log as $entry) {
            if (! is_array($entry) || ! isset($entry['to'])) {
                continue;
            }
            $entries[] = [
                'time' => (int) ($entry['time'] ?? 0),
                'user' => (int) ($entry['user'] ?? 0),
                'from' => (string) ($entry['from'] ?? ''),
                'to'   => (string) ($entry['to'] ?? ''),
            ];
        }

        return array_slice(array_reverse($entries), 0, max(1, $limit));
    }

    private function appendLog(string $from, string $to, int $userId): void
    {
        $this->append(['time' => time(), 'user' => $userId, 'from' => $from, 'to' => $to]);
    }

    /**
     * @param array<string,int|string> $row
     */
    private function append(array $row): void
    {
        $log = get_option(self::LOG, []);
        $log = is_array($log) ? $log : [];

        $log[] = $row;

        if (count($log) > self::MAX_LOG) {
            $log = array_slice($log, -self::MAX_LOG);
        }

        update_option(self::LOG, $log, false);
    }
}
