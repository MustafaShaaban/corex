<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * Each model service's health (spec 129, FR-005, FR-019): until when it is skipped, and how it last
 * failed. One small option, not autoloaded. The last failure is what Settings → Assistant shows, so a
 * service that keeps failing says how (a limit, a request too large, a wrong model), not only that it
 * did.
 *
 * The store is passed in as two closures so the rules are tested without WordPress;
 * {@see fromWordPress()} is the option.
 */
final class Cooldowns
{
    public const OPTION = 'corex_assistant_cooldowns';

    /** A failure older than this is no longer shown. */
    private const KEEP_SECONDS = 7 * 86400;

    /**
     * @param \Closure(): array<string, mixed>                   $read
     * @param \Closure(array<string, array<string, mixed>>): void $write
     * @param \Closure(): int                                    $now
     */
    public function __construct(
        private readonly \Closure $read,
        private readonly \Closure $write,
        private readonly \Closure $now,
    ) {
    }

    public static function fromWordPress(): self
    {
        return new self(
            static function (): array {
                $stored = get_option(self::OPTION, []);

                return is_array($stored) ? $stored : [];
            },
            static function (array $entries): void {
                update_option(self::OPTION, $entries, false);
            },
            static fn (): int => time(),
        );
    }

    public function isCooling(string $id): bool
    {
        return $this->until($id) > 0;
    }

    /** The moment the service may be tried again; 0 when it is not skipped. */
    public function until(string $id): int
    {
        $until = (int) ($this->entries()[$id]['until'] ?? 0);

        return $until > ($this->now)() ? $until : 0;
    }

    /**
     * How the service last failed in the past week, or null when it has not, or answered since.
     *
     * @return array{kind: string, status: int, detail: string, at: int}|null
     */
    public function lastFailure(string $id): ?array
    {
        $entry = $this->entries()[$id] ?? null;
        if ($entry === null || (string) ($entry['kind'] ?? '') === '') {
            return null;
        }

        return [
            'kind' => (string) $entry['kind'],
            'status' => (int) ($entry['status'] ?? 0),
            'detail' => (string) ($entry['detail'] ?? ''),
            'at' => (int) ($entry['at'] ?? 0),
        ];
    }

    /** Records the failure, and skips the service for as long as the failure says (maybe not at all). */
    public function cool(string $id, Failure $failure): void
    {
        $now = ($this->now)();
        $wait = $failure->waitSeconds($now);
        $entries = $this->entries();
        $entries[$id] = [
            'until' => $wait > 0 ? $now + $wait : 0,
            'kind' => $failure->kind,
            'status' => $failure->status,
            'detail' => $failure->detail,
            'at' => $now,
        ];
        ($this->write)($entries);
    }

    /** The service answered: nothing to skip, nothing to report. */
    public function clear(string $id): void
    {
        $entries = $this->entries();
        if (isset($entries[$id])) {
            unset($entries[$id]);
            ($this->write)($entries);
        }
    }

    /**
     * The stored entries that still matter: a rest not over, or a failure of the past week. An entry
     * that is a bare time (how slice 1 stored a rest) reads as a rest with no failure on record.
     *
     * @return array<string, array<string, mixed>>
     */
    private function entries(): array
    {
        $now = ($this->now)();
        $entries = [];
        foreach (($this->read)() as $id => $entry) {
            $entry = is_array($entry) ? $entry : ['until' => (int) $entry];
            if ((int) ($entry['until'] ?? 0) > $now || (int) ($entry['at'] ?? 0) > $now - self::KEEP_SECONDS) {
                $entries[(string) $id] = $entry;
            }
        }

        return $entries;
    }
}
