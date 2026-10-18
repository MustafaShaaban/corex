<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * The steps of an installation that the running release does, each a request or several (spec
 * 107, plan D8): unpack beside the site, hold each folder to the package's description, copy
 * the tables, and put the release in place.
 *
 * Nothing of the site moves before the last of them. That one is also the last thing the
 * running release does: the request after it is answered by the release it put in place.
 */
final class ReleaseSteps
{
    /** How long a request checks folders before it answers. */
    private const SECONDS = 8.0;

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseStaging $staging,
        private readonly ReleaseTableCopies $copies,
        private readonly ReleaseSwap $swap,
        private readonly ReleaseJournal $journal,
        private readonly ReleaseSafeguards $safeguards,
        private readonly InstalledRelease $installed,
        private readonly float $seconds = self::SECONDS,
    ) {
    }

    /**
     * Do the next piece of the step an installation stands at.
     *
     * @return ReleaseProgress Where it stands afterwards: further into the step, or at the next.
     *
     * @throws ReleaseRefused When the step cannot be done. Unless the reason is
     *                        `ReleaseSwap::HALF_DONE`, the site is as it was before the
     *                        installation began.
     */
    public function next(ReleaseProgress $progress, DateTimeImmutable $now): ReleaseProgress
    {
        return match ($progress->step) {
            ReleaseProgress::UNPACK      => $this->unpack($progress),
            ReleaseProgress::VERIFY      => $this->verify($progress),
            ReleaseProgress::COPY_TABLES => $this->copyTables($progress),
            ReleaseProgress::SWAP        => $this->putInPlace($progress, $now),
            default                      => $progress,
        };
    }

    /**
     * Whether an installation has moved no folder of the site (FR-022a): it stands before the
     * swap, or at it with no rename of its own made. A journal that is not this installation's,
     * the one of the installation before or none, is of a swap this one has not begun.
     */
    public function hasMovedNothing(ReleaseProgress $progress): bool
    {
        if ($progress->isBeforeTheSwap()) {
            return true;
        }

        return $progress->step === ReleaseProgress::SWAP
            && (! $this->journal->isOf($progress->keyHash) || $this->swap->hasMovedNothing());
    }

    /**
     * Give up an installation that has changed nothing of the site: let visitors in where it
     * had begun to put the release in place, and remove its way back, what it unpacked, and the
     * table copies if it had begun making them.
     *
     * The copies and the way back of the installation before are left where this one has not
     * replaced them: until it copies and swaps, they are still that one's, and still good.
     */
    public function abandon(ReleaseProgress $progress): void
    {
        if ($progress->step === ReleaseProgress::SWAP) {
            $this->safeguards->withdraw($progress->keyHash);
        }

        $this->staging->setAside();

        $beganCopying = $progress->step === ReleaseProgress::SWAP
            || ($progress->step === ReleaseProgress::COPY_TABLES && $progress->cursor > 0);

        if ($beganCopying) {
            $this->copies->discard();
        }
    }

    private function unpack(ReleaseProgress $progress): ReleaseProgress
    {
        $unpacked = $this->staging->unpack(
            $this->store->fileIn('incoming', $progress->package),
            $progress->manifest(),
            $progress->cursor,
        );

        return $unpacked['cursor'] < $unpacked['of']
            ? $progress->at(ReleaseProgress::UNPACK, $unpacked['cursor'], $unpacked['of'])
            : $progress->at(ReleaseProgress::VERIFY, 0, count($progress->manifest()->releasePaths));
    }

    /** At least one folder a request, and as many as there is time for. */
    private function verify(ReleaseProgress $progress): ReleaseProgress
    {
        $manifest = $progress->manifest();
        $folders  = $manifest->releasePaths;
        $cursor   = $progress->cursor;
        $until    = microtime(true) + $this->seconds;

        while ($cursor < count($folders)) {
            $this->staging->verify($manifest, $folders[$cursor]);
            $cursor++;

            if (microtime(true) >= $until) {
                break;
            }
        }

        return $cursor < count($folders)
            ? $progress->at(ReleaseProgress::VERIFY, $cursor, count($folders))
            : $progress->at(ReleaseProgress::COPY_TABLES);
    }

    private function copyTables(ReleaseProgress $progress): ReleaseProgress
    {
        // Every copy of an installation carries the minute it was prepared, whichever request makes it.
        $copied = $this->copies->copy($progress->cursor, new DateTimeImmutable($progress->preparedAt));

        return $copied['cursor'] < $copied['of']
            ? $progress->at(ReleaseProgress::COPY_TABLES, $copied['cursor'], $copied['of'])
            : $progress->at(ReleaseProgress::SWAP);
    }

    /**
     * The swap, or the rest of one a request was cut off in the middle of.
     *
     * @throws ReleaseRefused
     */
    private function putInPlace(ReleaseProgress $progress, DateTimeImmutable $now): ReleaseProgress
    {
        if (! $this->hasFinishedASwapItBegan($progress)) {
            $this->safeguards->raise($progress->keyHash, $now->getTimestamp());

            try {
                $this->swap->swap($progress->manifest(), $this->installed->ownedFolders(), $this->about($progress, $now));
            } catch (ReleaseRefused $refused) {
                if ($refused->reason === ReleaseSwap::FAILED) {
                    $this->safeguards->remove();
                }

                throw $refused;
            }
        }

        // The files PHP has compiled are the release that was just moved out.
        if (function_exists('opcache_reset')) {
            @opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a host that restricts it warns as well as refusing.
        }

        return $progress->at(ReleaseProgress::FINISH);
    }

    /**
     * Whether this installation began a swap in an earlier request, which is whole by now.
     *
     * The journal on the site may be the one of the installation before. It is this one's when
     * it carries this one's mark. Then a request began the swap and did not live to say so:
     * it was cut off between two renames, which is settled here, or after the last of them.
     *
     * @return bool False when this installation has begun no swap.
     *
     * @throws ReleaseRefused When the swap it began was undone, or could be neither finished nor undone.
     */
    private function hasFinishedASwapItBegan(ReleaseProgress $progress): bool
    {
        if (! $this->journal->isOf($progress->keyHash)) {
            return false;
        }

        $outcome = match ($this->journal->status()) {
            ReleaseJournal::SWAPPED => ReleaseSwap::FINISHED,
            ReleaseJournal::OPEN    => $this->swap->settle(),
            ReleaseJournal::UNDONE  => ReleaseSwap::UNDONE,
            default                 => ReleaseSwap::STUCK,
        };

        if ($outcome === ReleaseSwap::FINISHED) {
            return true;
        }

        if ($outcome === ReleaseSwap::UNDONE) {
            $this->safeguards->remove();

            throw new ReleaseRefused(ReleaseSwap::FAILED, sprintf(
                /* translators: %s: a folder's path on the server. */
                __('The installation was cut off while folders were being moved, and one of them would not move when it was gone on with (%s). Every folder is back where it was: the site is the release it was. Check the host lets the site rename folders in wp-content, and install again.', 'corex'),
                $this->journal->refused(),
            ));
        }

        throw new ReleaseRefused(
            ReleaseSwap::HALF_DONE,
            __('The installation was cut off while folders were being moved, and could be neither finished nor undone. The site is part one release and part the other, and visitors are held off. The recovery link puts the previous release back.', 'corex'),
        );
    }

    /**
     * What the journal keeps of this swap, for going back and for the screen: which
     * installation it is of, what it put in place and what it replaced.
     *
     * @return array<string,mixed>
     */
    private function about(ReleaseProgress $progress, DateTimeImmutable $now): array
    {
        return [
            'for'       => $progress->keyHash,
            'installed' => $progress->manifest()->described(),
            'replaced'  => $progress->replaced,
            'by'        => $progress->by,
            'at'        => $now->format(DATE_ATOM),
        ];
    }
}
