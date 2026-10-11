<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Closure;
use Corex\Config\Retention\PrunableStore;
use DateTimeImmutable;

/**
 * How long a Data export's file is kept (spec 109, FR-013).
 *
 * An export is a copy of a site's records sitting on the server. Nothing removed a Data export's
 * file: the thirty days were the Submissions export's alone, while the notification for either
 * said the file "stays available until the export retention window ends". The file goes after
 * the same thirty days; its entry in the history stays.
 *
 * An export's expiry is not stored. It is this period counted from the day the export was made,
 * so the date the history shows and the day the sweep removes the file cannot disagree.
 */
final readonly class DataExportRetention implements PrunableStore
{
    private const DAYS = 30;

    /** A sweep that found more than this would hold its request for too long; the next one goes on. */
    private const MOST_REMOVED_IN_ONE_SWEEP = 200;

    /** @param Closure():DateTimeImmutable $now */
    public function __construct(
        private DataExportStore $exports,
        private Closure $now,
    ) {
    }

    public function key(): string
    {
        return 'data_exports';
    }

    public function label(): string
    {
        return __('Data export files', 'corex');
    }

    public function retentionDays(): int
    {
        return self::DAYS;
    }

    public function expiryOf(DataExportRun $run): DateTimeImmutable
    {
        return $run->createdAt->modify('+' . self::DAYS . ' days');
    }

    public function hasExpired(DataExportRun $run): bool
    {
        return ($this->now)() >= $this->expiryOf($run);
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        $runs = $this->exports->holdingFilesBefore($cutoff, self::MOST_REMOVED_IN_ONE_SWEEP);
        foreach ($runs as $run) {
            $this->exports->removeFile($run->id, DataExportRun::REMOVED_EXPIRED, 0);
        }

        return count($runs);
    }
}
