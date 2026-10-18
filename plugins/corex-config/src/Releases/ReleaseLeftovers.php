<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What an installation left on the site when no installation is in progress any more (spec
 * 107, FR-022b).
 *
 * A request cut off by the host, or a release older than this one that could not give an
 * installation up, can leave its pieces behind with no record of the installation they were
 * of. Nothing uses them, and the screen says so, by name, rather than "No installation is in
 * progress" beside them:
 *
 *     staging           a release unpacked for an installation that is not there
 *     recovery_file     the recovery plugin, asking for a key that is neither an installation's
 *                       nor the kept release's: there is nothing it could put back
 *     previous_unknown  folders in `previous/` with no journal saying what they are: there is
 *                       no going back to them
 *
 * They go when the administrator says so, not when the screen opens: one of them is a
 * must-use plugin, which the administrator is told of before it is removed.
 */
final class ReleaseLeftovers
{
    public const STAGING          = 'staging';
    public const RECOVERY_FILE    = 'recovery_file';
    public const PREVIOUS_UNKNOWN = 'previous_unknown';

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseStaging $staging,
        private readonly ReleaseJournal $journal,
        private readonly ReleaseRecoveryPlugin $recovery,
    ) {
    }

    /**
     * What is left, by what it is and where it is on the server. None while an installation is
     * in progress: then all of it is that installation's.
     *
     * @return list<array{what:string,where:string}>
     *
     * @throws ReleaseRefused When an installation is in progress that this release cannot read.
     */
    public function found(): array
    {
        if (ReleaseProgress::standing($this->store) !== null) {
            return [];
        }

        $found = [];
        // Asked when the screen opens, on a host where the installer's place may not exist.
        // Where it does not, nothing is kept and nothing is staged, and asking must not make it.
        $placed = $this->store->exists();

        if ($placed && $this->holdsAnything('staging')) {
            $found[] = ['what' => self::STAGING, 'where' => $this->store->folder('staging')];
        }

        if ($this->recovery->isWritten() && ! ($placed && $this->isTheKeptReleasesWayBack($this->recovery->keyHash()))) {
            $found[] = ['what' => self::RECOVERY_FILE, 'where' => $this->recovery->file()];
        }

        if ($placed && $this->journal->status() === null && $this->holdsAnything('previous')) {
            $found[] = ['what' => self::PREVIOUS_UNKNOWN, 'where' => $this->store->folder('previous')];
        }

        return $found;
    }

    /**
     * Remove what is left. What a folder holds is set aside by one rename, and deleted a few
     * seconds at a time when the screen next opens, as a kept release is (FR-024b).
     *
     * @return list<array{what:string,where:string}> What was removed.
     *
     * @throws ReleaseRefused When an installation is in progress: what it holds is not left over.
     */
    public function remove(): array
    {
        return $this->store->exclusively(function (): array {
            if (ReleaseProgress::standing($this->store) !== null) {
                throw new ReleaseRefused(
                    ReleaseInstallation::IN_PROGRESS,
                    __('An installation is in progress on this site, so nothing it holds is left over. Open Releases again to see where it stands.', 'corex'),
                );
            }

            $found = $this->found();

            foreach ($found as $leftover) {
                match ($leftover['what']) {
                    self::STAGING          => $this->staging->setAside(),
                    self::RECOVERY_FILE    => $this->recovery->remove(),
                    self::PREVIOUS_UNKNOWN => $this->store->setAside('previous'),
                };
            }

            return $found;
        });
    }

    /** The recovery plugin is the way back to the kept release when it asks for the key of the swap that kept it. */
    private function isTheKeptReleasesWayBack(?string $keyHash): bool
    {
        $keeps = in_array($this->journal->status(), [ReleaseJournal::SWAPPED, ReleaseJournal::STUCK], true);

        return $keeps && $keyHash !== null && $this->journal->isOf($keyHash);
    }

    private function holdsAnything(string $area): bool
    {
        return array_diff(scandir($this->store->folder($area)) ?: [], ['.', '..']) !== [];
    }
}
