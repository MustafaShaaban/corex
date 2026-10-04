<?php

/**
 * Puts back the transients a test writes, so a run leaves none behind and changes none.
 *
 * The integration suite runs against a developer's real install. A test that submits a form goes
 * through the rate limiter, which counts in a transient; a test that previews a migration stores
 * the preview in one. Each expires within minutes, and its rows then stay in the options table
 * until WordPress next sweeps expired transients. A transient is also not always the test's to
 * delete: the `contact` form's counter is keyed by form and client, so a second run inside the
 * window finds the first run's counter and raises it.
 *
 * So this restores, it does not delete: a transient that was absent is removed, and one that was
 * there gets the value and the expiry it had. The options API reports both — `add_option` fires
 * only for a row that does not exist yet, `update_option` hands over the value being replaced.
 *
 *     beforeEach(fn () => $this->transients = WatchedTransients::watch('corex_throttle_'));
 *     afterEach(fn () => $this->transients->restore());
 *
 * With a persistent object cache WordPress keeps transients in the cache, not in options. There
 * is then nothing in the database to put back, and this sees nothing.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

final class WatchedTransients
{
    /** @var array<string,array{existed:bool,value:mixed}> option name => what it held before the test wrote it */
    private array $before = [];

    /** @param list<string> $optionPrefixes */
    private function __construct(private readonly array $optionPrefixes)
    {
    }

    /** @param string ...$prefixes Transient name prefixes, e.g. `corex_throttle_`. */
    public static function watch(string ...$prefixes): self
    {
        $optionPrefixes = [];
        foreach ($prefixes as $prefix) {
            $optionPrefixes[] = '_transient_' . $prefix;
            $optionPrefixes[] = '_transient_timeout_' . $prefix;
        }

        $watcher = new self($optionPrefixes);
        add_action('add_option', [$watcher, 'rememberAbsent'], 10, 1);
        add_action('update_option', [$watcher, 'rememberValue'], 10, 2);

        return $watcher;
    }

    /** The `add_option` listener: the row is about to be created, so there was none. */
    public function rememberAbsent(string $option): void
    {
        if ($this->watches($option) && ! isset($this->before[$option])) {
            $this->before[$option] = ['existed' => false, 'value' => null];
        }
    }

    /** The `update_option` listener: only the first write counts, later ones replace the test's own. */
    public function rememberValue(string $option, mixed $oldValue): void
    {
        if ($this->watches($option) && ! isset($this->before[$option])) {
            $this->before[$option] = ['existed' => true, 'value' => $oldValue];
        }
    }

    /** Stop watching and put every transient the test wrote back as it was. */
    public function restore(): void
    {
        remove_action('add_option', [$this, 'rememberAbsent'], 10);
        remove_action('update_option', [$this, 'rememberValue'], 10);

        foreach ($this->before as $option => $was) {
            if ($was['existed']) {
                // Not autoloaded, which is how WordPress stores a transient that expires.
                update_option($option, $was['value'], false);
            } else {
                delete_option($option);
            }
        }

        $this->before = [];
    }

    private function watches(string $option): bool
    {
        foreach ($this->optionPrefixes as $prefix) {
            if (str_starts_with($option, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
