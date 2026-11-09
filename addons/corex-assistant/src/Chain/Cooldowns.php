<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * Until when each model service is skipped (spec 129, FR-005): one small option, not autoloaded.
 *
 * The store is passed in as two closures so the rules are tested without WordPress;
 * {@see fromWordPress()} is the option.
 */
final class Cooldowns
{
    public const OPTION = 'corex_assistant_cooldowns';

    /**
     * @param \Closure(): array<string, int>     $read
     * @param \Closure(array<string, int>): void $write
     * @param \Closure(): int                    $now
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

                return is_array($stored) ? array_map('intval', $stored) : [];
            },
            static function (array $until): void {
                update_option(self::OPTION, $until, false);
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
        $until = ($this->read)()[$id] ?? 0;

        return $until > ($this->now)() ? $until : 0;
    }

    public function cool(string $id, Failure $failure): void
    {
        $now = ($this->now)();
        $wait = $failure->waitSeconds($now);
        if ($wait === 0) {
            return;
        }
        $all = array_filter(($this->read)(), static fn (int $until): bool => $until > $now);
        $all[$id] = $now + $wait;
        ($this->write)($all);
    }

    public function clear(string $id): void
    {
        $all = ($this->read)();
        if (isset($all[$id])) {
            unset($all[$id]);
            ($this->write)($all);
        }
    }
}
