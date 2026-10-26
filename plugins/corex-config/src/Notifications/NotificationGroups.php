<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Notifications\Notification;

/**
 * Which unread notifications are shown as one item (spec 118, US-SHL-09). A fixed list of kinds
 * that only report: finished exports and failed jobs, grouped by the kind of job, so 25 finished
 * exports of submissions are "25 exports finished" and do not bury the notice that needs a person.
 * A notification that asks for a decision (an access request, an assignment, a failed email to a
 * person, a readiness blocker) is not on the list and is never grouped.
 *
 * Grouping is a way of showing. Nothing is merged or deleted in storage; each member keeps its own
 * state, and retention treats each one as before. Pure, so it is unit-testable.
 */
final class NotificationGroups
{
    private const GROUPED = ['export.ready', 'job.failed'];

    /** The group a notification belongs to, or null for one that is shown on its own. */
    public function keyOf(Notification $notification): ?string
    {
        if (! in_array($notification->type, self::GROUPED, true)) {
            return null;
        }

        return $notification->type . '|' . (string) ($notification->metadata['job_kind'] ?? '');
    }

    /** The title of a group of `$count`, in the language's plural forms. */
    public function title(string $type, int $count): string
    {
        $number = number_format_i18n($count);

        return $type === 'job.failed'
            ? sprintf(
                /* translators: %s: how many background jobs did not finish. */
                _n('%s job did not finish', '%s jobs did not finish', $count, 'corex'),
                $number,
            )
            : sprintf(
                /* translators: %s: how many exports finished. */
                _n('%s export finished', '%s exports finished', $count, 'corex'),
                $number,
            );
    }
}
