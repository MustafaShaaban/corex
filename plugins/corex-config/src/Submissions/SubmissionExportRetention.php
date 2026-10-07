<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Closure;
use Corex\Config\Retention\PrunableStore;
use DateTimeImmutable;

/**
 * How long an exported file is kept (spec 103, FR-030).
 *
 * An export is a copy of people's answers sitting on the server. It is kept long enough to be
 * downloaded again and then removed: the file goes, the entry in the history stays. The daily
 * retention sweep does the removing, with every other store it cleans.
 *
 * An export's expiry is not stored. It is this period counted from the day the export was made,
 * so the date the history shows and the day the sweep removes the file cannot disagree.
 */
final readonly class SubmissionExportRetention implements PrunableStore
{
    private const DAYS = 30;

    /** A sweep that found more than this would hold its request for too long; the next one goes on. */
    private const MOST_REMOVED_IN_ONE_SWEEP = 200;

    /** @param Closure():DateTimeImmutable $now */
    public function __construct(
        private SubmissionExportStore $exports,
        private Closure $now,
    ) {
    }

    public function key(): string
    {
        return 'submission_exports';
    }

    public function label(): string
    {
        return __('Submission export files', 'corex');
    }

    public function retentionDays(): int
    {
        return self::DAYS;
    }

    public function expiryOf(SubmissionExportRun $run): DateTimeImmutable
    {
        return $run->createdAt->modify('+' . self::DAYS . ' days');
    }

    public function hasExpired(SubmissionExportRun $run): bool
    {
        return ($this->now)() >= $this->expiryOf($run);
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        $runs = $this->exports->holdingFilesBefore($cutoff, self::MOST_REMOVED_IN_ONE_SWEEP);
        foreach ($runs as $run) {
            $this->exports->removeFile($run->id, SubmissionExportRun::REMOVED_EXPIRED, 0);
        }

        return count($runs);
    }
}
