<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Closure;

/**
 * A release put in the place of the one that is running (spec 107, plan D6; FR-020, FR-022,
 * FR-026).
 *
 * By renames, which a filesystem makes whole or not at all: each folder of the running release
 * is moved out to `previous/`, and the staged folder moved in. A folder the running release
 * owned and this one does not hold is moved out as well. A folder the package does not name is
 * never touched, whatever it is.
 *
 * Every rename is in the journal before it is made. Cut off between two of them, the site is
 * part one release and part the other, and the next request settles it: it finishes the swap,
 * or, when a folder will not move, puts back every one that did.
 */
final class ReleaseSwap
{
    public const FAILED = 'swap_failed';

    /** A folder would not move, and one that had moved would not go back: the site is not as it was. */
    public const HALF_DONE = 'swap_half_done';

    /**
     * What `settle()` answers: the swap that was cut off is now whole; or is undone, every
     * folder back; or is stuck, with a folder that would neither move nor go back.
     */
    public const FINISHED = 'finished';
    public const UNDONE   = 'undone';
    public const STUCK    = 'stuck';

    /** @var Closure(string,string):bool */
    private readonly Closure $rename;

    /**
     * @param string                           $siteRoot The folder a release's paths are relative to.
     * @param (Closure(string,string):bool)|null $rename   Moves a folder; PHP's own, unless a test needs one to fail.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseStaging $staging,
        private readonly ReleaseJournal $journal,
        private readonly string $siteRoot,
        ?Closure $rename = null,
    ) {
        // A folder that will not move is an answer here, not a warning in the response.
        $this->rename = $rename ?? static fn (string $from, string $to): bool => @rename($from, $to); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
    }

    /**
     * Put the staged release in place.
     *
     * What the last installation replaced is discarded first: one previous release is kept.
     *
     * @param list<string> $ownedBefore The folders the running release recorded as its own.
     *
     * @throws ReleaseRefused When a folder would not move. Every folder that had moved is back where it was.
     */
    public function swap(ReleaseManifest $manifest, array $ownedBefore): void
    {
        $this->store->empty('previous');
        $this->journal->begin($this->movesFor($manifest, $ownedBefore));

        $outcome = $this->run();

        if ($outcome === self::FINISHED) {
            return;
        }

        if ($outcome === self::UNDONE) {
            throw new ReleaseRefused(self::FAILED, sprintf(
                /* translators: %s: a folder's path on the server. */
                __('A folder could not be moved, so the release was not installed and the site is as it was: %s. Check the host lets the site rename folders in wp-content, and install again.', 'corex'),
                $this->journal->refused(),
            ));
        }

        throw new ReleaseRefused(self::HALF_DONE, sprintf(
            /* translators: 1: the folder that could not be moved. 2: folders that are not where they belong, separated by commas. */
            __('A folder could not be moved (%1$s), and what had already been moved would not all go back. The site is part one release and part the other. These are not where they belong: %2$s. The recovery link puts them back; so does moving each one back by hand.', 'corex'),
            $this->journal->refused(),
            implode(', ', $this->misplaced()),
        ));
    }

    /**
     * Settle a swap a request was cut off in the middle of.
     *
     * @return string|null `FINISHED`, `UNDONE` or `STUCK`; null when no swap was left open.
     */
    public function settle(): ?string
    {
        if ($this->journal->status() !== ReleaseJournal::OPEN) {
            return null;
        }

        $this->askTheFoldersWhatWasStarted();

        return $this->run();
    }

    /** Go back: every rename the last swap made, made the other way, last first. */
    public function undo(): void
    {
        $this->journal->close($this->putBack() === [] ? ReleaseJournal::UNDONE : ReleaseJournal::STUCK);
    }

    /**
     * Make every rename that has not been made, in order.
     *
     * @return string `FINISHED`; `UNDONE` when one failed and those before it were reversed; `STUCK` when one of those would not be.
     */
    private function run(): string
    {
        foreach ($this->journal->moves() as $index => $move) {
            if ($move['state'] === ReleaseJournal::DONE) {
                continue;
            }

            $this->journal->mark($index, ReleaseJournal::STARTED);
            wp_mkdir_p(dirname($move['to']));

            if (! ($this->rename)($move['from'], $move['to'])) {
                $this->journal->mark($index, ReleaseJournal::PENDING);
                $wentBack = $this->putBack() === [];
                $this->journal->close($wentBack ? ReleaseJournal::UNDONE : ReleaseJournal::STUCK, $move['from']);

                return $wentBack ? self::UNDONE : self::STUCK;
            }

            $this->journal->mark($index, ReleaseJournal::DONE);
        }

        $this->journal->close(ReleaseJournal::SWAPPED);

        return self::FINISHED;
    }

    /**
     * Reverse every rename that was made, last first.
     *
     * @return list<string> The folders that would not go back; none, when the site is as it was.
     */
    private function putBack(): array
    {
        $stuck = [];
        $moves = $this->journal->moves();

        foreach (array_reverse($moves, true) as $index => $move) {
            if ($move['state'] !== ReleaseJournal::DONE) {
                continue;
            }

            wp_mkdir_p(dirname($move['from']));

            if (($this->rename)($move['to'], $move['from'])) {
                $this->journal->mark($index, ReleaseJournal::PENDING);
            } else {
                $stuck[] = $move['to'];
            }
        }

        return $stuck;
    }

    /**
     * The folders that are still where a swap put them, after one that could not be undone.
     *
     * @return list<string>
     */
    private function misplaced(): array
    {
        $misplaced = [];

        foreach ($this->journal->moves() as $move) {
            if ($move['state'] === ReleaseJournal::DONE) {
                $misplaced[] = $move['to'];
            }
        }

        return $misplaced;
    }

    /**
     * A rename marked `started` was being made when its request died. Whether it was made is
     * read off the folders: it was, if the folder is gone from where it was and is where it
     * was going.
     */
    private function askTheFoldersWhatWasStarted(): void
    {
        foreach ($this->journal->moves() as $index => $move) {
            if ($move['state'] !== ReleaseJournal::STARTED) {
                continue;
            }

            $made = ! file_exists($move['from']) && file_exists($move['to']);
            $this->journal->mark($index, $made ? ReleaseJournal::DONE : ReleaseJournal::PENDING);
        }
    }

    /**
     * Each rename of the swap, in order: for a folder of the release, the running one out and
     * the staged one in; for a folder the running release owned and this one does not hold, out.
     *
     * @param list<string> $ownedBefore
     *
     * @return list<array{from:string,to:string}>
     */
    private function movesFor(ReleaseManifest $manifest, array $ownedBefore): array
    {
        $moves = [];

        foreach ($manifest->releasePaths as $path) {
            if (is_dir($this->live($path))) {
                $moves[] = $this->out($path);
            }
            $moves[] = ['from' => $this->staging->folderOf($path), 'to' => $this->live($path)];
        }

        // What the running release recorded is a stored value: only a folder a release can
        // own is ever moved on its word.
        $dropped = array_filter(
            array_diff($ownedBefore, $manifest->releasePaths),
            fn (string $path): bool => ReleaseManifest::owns($path) && is_dir($this->live($path)),
        );

        return [...$moves, ...array_map($this->out(...), array_values($dropped))];
    }

    /**
     * @return array{from:string,to:string}
     */
    private function out(string $path): array
    {
        return ['from' => $this->live($path), 'to' => $this->store->folder('previous') . '/' . $path];
    }

    private function live(string $path): string
    {
        return rtrim($this->siteRoot, '/\\') . '/' . $path;
    }
}
