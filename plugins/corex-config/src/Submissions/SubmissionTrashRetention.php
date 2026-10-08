<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Retention\PrunableStore;
use Corex\Config\Retention\RetentionSettings;
use DateTimeImmutable;

/**
 * How long the trash keeps a submission, and the emptying of it (spec 105, US3).
 *
 * WordPress empties its own trash 30 days after a post went into it, for every post type, with
 * nothing recorded and a file uploaded with a submission left on disk. A submission in the trash
 * is CoreX's to delete: after the number of days the site set, with everything tied to it, and
 * with an entry in the activity stream. 0 means the trash keeps a submission until somebody
 * deletes it.
 *
 * A submission's date is not stored. It is this period counted from the day it was trashed, so
 * the date the trash shows and the day the sweep deletes cannot disagree.
 */
final readonly class SubmissionTrashRetention implements PrunableStore
{
    public const OPTION = 'corex_trash_submissions_days';

    /** WordPress's own default, so a site's trash does not start behaving differently unasked. */
    public const DEFAULT_DAYS = 30;

    /** A sweep that found more than this would hold its request for too long; the next one goes on. */
    private const MOST_DELETED_IN_ONE_SWEEP = 100;

    public function __construct(
        private SubmissionTrashStore $trash,
        private SubmissionTrashService $service,
        private RetentionSettings $settings,
    ) {
    }

    public function key(): string
    {
        return 'submission_trash';
    }

    public function label(): string
    {
        return __('Trashed submissions', 'corex');
    }

    public function retentionDays(): int
    {
        return $this->settings->sanitizeDays(get_option(self::OPTION, self::DEFAULT_DAYS));
    }

    public function setDays(int $days): int
    {
        $clean = $this->settings->sanitizeDays($days);
        update_option(self::OPTION, $clean, false);

        return $clean;
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        // What was trashed the WordPress way, before the inbox had a trash, is taken onto this
        // clock first: it has WordPress's date and none of CoreX's.
        $this->trash->adoptWordPressTrash(self::MOST_DELETED_IN_ONE_SWEEP);

        return $this->service->expire($this->trash->trashedBefore($cutoff, self::MOST_DELETED_IN_ONE_SWEEP));
    }
}
