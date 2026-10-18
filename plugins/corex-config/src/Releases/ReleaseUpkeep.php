<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * What the installer tidies when the Releases screen is opened (spec 107, FR-003, FR-044,
 * FR-045; plan D10).
 *
 * Nothing here runs on a schedule: the host this is written for allows no new scheduled job.
 * So what has to go after a time goes when somebody next looks. A copy of the tables that has
 * been kept its days, a package nobody installed, a return made through the recovery link
 * that CoreX has not heard of yet, and the release before the previous one, which an
 * installation sets aside and does not delete while visitors are held off (FR-024b): a few
 * seconds of it each time, until it is gone.
 */
final class ReleaseUpkeep
{
    /** How long a package, or part of one, is kept on the site without being installed. */
    public const PACKAGE_DAYS = 7;

    private const A_DAY = 86400;

    /** How long opening the screen spends deleting what an installation set aside. */
    private const DISCARDING_SECONDS = 4.0;

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseReturn $return,
        private readonly ReleaseTableCopies $copies,
    ) {
    }

    /**
     * @throws ReleaseRefused When an installation is in progress that this release cannot read.
     */
    public function whenTheScreenOpens(DateTimeImmutable $now): void
    {
        // The screen opens on a host where the place cannot be made, to say so.
        if (! $this->store->exists()) {
            return;
        }

        try {
            $this->store->exclusively(function () use ($now): void {
                $this->return->takeInOneMadeByTheLink($now);
                $this->copies->discardExpired($now);
                $this->store->discard(self::DISCARDING_SECONDS);
                $this->store->discardUnused(
                    $now->getTimestamp() - self::PACKAGE_DAYS * self::A_DAY,
                    (string) ReleaseProgress::standing($this->store)?->package,
                );
            });
        } catch (ReleaseRefused $refused) {
            // A request of an installation is at work: it is not tidied around. Next time.
            if ($refused->reason !== ReleaseStore::BUSY) {
                throw $refused;
            }
        }
    }
}
