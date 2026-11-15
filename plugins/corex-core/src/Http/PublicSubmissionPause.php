<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;

/**
 * Whether public submissions are closed to the current visitor, by which mode, and what to tell
 * them (spec 112, User Story 3; spec 124, FR-004).
 *
 * One answer for every place that asks, so Maintenance and Forms paused cannot disagree about who
 * passes: corex-config's gate refuses the routes that declare {@see PublicSubmission::MARKER}.
 * Maintenance and Forms paused close submissions; every other mode leaves them open, Coming soon
 * included (spec 101). A signed-in administrator is never closed out, so the owner can test a form
 * in either mode, and `corex_maintenance_bypass` still opens Maintenance (DECISIONS #123).
 *
 * The mode is read from its option, as {@see SiteEnvironment} reads it: both closing modes are
 * visitor modes, which no lock takes away, so the stored value is the mode in force.
 */
final class PublicSubmissionPause
{
    public const MAINTENANCE = 'maintenance';

    public const FORMS_PAUSED = 'forms-paused';

    /** What a client is told to wait, in seconds, until the back-by time of spec 124's slice 9. */
    private const RETRY_AFTER_SECONDS = 3600;

    /** The mode that closes submissions to this visitor, or null while they are open. */
    public function closedBy(): ?string
    {
        $mode = get_option(SiteEnvironment::MODE_OPTION, '');
        if (! in_array($mode, [self::MAINTENANCE, self::FORMS_PAUSED], true)) {
            return null;
        }

        if ($mode === self::MAINTENANCE && apply_filters('corex_maintenance_bypass', false) === true) {
            return null;
        }

        // Never close submissions to a signed-in administrator: the operator cannot lock themselves out.
        return is_user_logged_in() && current_user_can('manage_options') ? null : $mode;
    }

    /**
     * The refusal's code. Maintenance keeps spec 112's, which client sites match by exact string.
     */
    public function code(string $mode): string
    {
        return $mode === self::MAINTENANCE ? 'site_in_maintenance' : 'forms_paused';
    }

    /** What a visitor is told when a submission is refused in this mode. */
    public function message(string $mode): string
    {
        return $mode === self::MAINTENANCE
            ? __('The site is closed for maintenance and is not taking submissions. Please try again later.', 'corex')
            : __('Forms are paused for a short while. Please try again later.', 'corex');
    }

    /** The seconds a refused client is told to wait. */
    public function retryAfter(): int
    {
        return self::RETRY_AFTER_SECONDS;
    }
}
