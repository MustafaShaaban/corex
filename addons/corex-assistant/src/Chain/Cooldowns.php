<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * Until when each model service is skipped, and why it last failed (spec 129, FR-005): one small option,
 * not autoloaded. The failure stays a week, so the owner's screen can say what went wrong after the rest
 * is over; a service that answers again clears it.
 *
 * The store is passed in as two closures so the rules are tested without WordPress;
 * {@see fromWordPress()} is the option.
 */
final class Cooldowns
{
    public const OPTION = 'corex_assistant_cooldowns';

    private const KEEP_SECONDS = 7 * 86400;

    /**
     * @param \Closure(): array<string, mixed>     $read
     * @param \Closure(array<string, mixed>): void $write
     * @param \Closure(): int                      $now
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
     * Why the service last failed, while that is less than a week old and it has not answered since.
     *
     * @return array{kind: string, status: int, detail: string, at: int}|null
     */
    public function lastFailure(string $id): ?array
    {
        $entry = $this->entries()[$id] ?? null;
        if (! is_array($entry) || (string) ($entry['kind'] ?? '') === '') {
            return null;
        }

        return [
            'kind' => (string) $entry['kind'],
            'status' => (int) ($entry['status'] ?? 0),
            'detail' => (string) ($entry['detail'] ?? ''),
            'at' => (int) ($entry['at'] ?? 0),
        ];
    }

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

    public function clear(string $id): void
    {
        $entries = ($this->read)();
        if (isset($entries[$id])) {
            unset($entries[$id]);
            ($this->write)($entries);
        }
    }

    /** @return array<string, array<string, mixed>> the entries still resting or still worth showing */
    private function entries(): array
    {
        $now = ($this->now)();
        $entries = [];
        foreach (($this->read)() as $id => $entry) {
            // A bare time is how slice 1 kept a rest.
            $entry = is_array($entry) ? $entry : ['until' => (int) $entry];
            if ((int) ($entry['until'] ?? 0) > $now || (int) ($entry['at'] ?? 0) > $now - self::KEEP_SECONDS) {
                $entries[(string) $id] = $entry;
            }
        }

        return $entries;
    }
}
