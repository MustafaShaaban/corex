<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use Corex\Operations\VisitorMessage;
use Corex\Operations\VisitorMessages;

/**
 * Whether public submissions are closed to the current visitor, by which mode, and what to tell
 * them (spec 112, spec 124).
 *
 * One answer for every place that asks: corex-config's gate refuses the routes that declare
 * {@see PublicSubmission::MARKER}, and corex-forms' flow block shows the paused panel instead of the
 * form. Maintenance and Forms paused close submissions; every other mode leaves them open, Coming
 * soon included (spec 101). A signed-in administrator is never closed out, so the owner can test a
 * form in either mode, and `corex_maintenance_bypass` still opens Maintenance (DECISIONS #123).
 *
 * The mode is read from its option, as {@see SiteEnvironment} reads it: both closed modes are
 * visitor modes, which no lock takes away, so the stored value is the mode in force.
 */
final class PublicSubmissionPause
{
    public const MAINTENANCE  = VisitorMessages::MAINTENANCE;
    public const FORMS_PAUSED = VisitorMessages::FORMS_PAUSED;

    public function __construct(private readonly VisitorMessages $messages)
    {
    }

    /** The mode that closes submissions to this visitor, or null while they are open. */
    public function closedBy(): ?string
    {
        $mode = get_option(SiteEnvironment::MODE_OPTION, '');
        if (! in_array($mode, VisitorMessages::MODES, true)) {
            return null;
        }

        if ($mode === self::MAINTENANCE && apply_filters('corex_maintenance_bypass', false) === true) {
            return null;
        }

        return is_user_logged_in() && current_user_can('manage_options') ? null : $mode;
    }

    public function notice(string $mode): VisitorMessage
    {
        return $this->messages->for($mode);
    }

    /** What a visitor is told when a submission is refused in this mode. */
    public function message(string $mode): string
    {
        if ($mode === self::MAINTENANCE) {
            return __('The site is closed for maintenance and is not taking submissions. Please try again later.', 'corex');
        }

        $notice = $this->notice($mode);
        $text   = $notice->messageFor(determine_locale(), get_locale());
        $text   = $text !== '' ? $text : __('Forms are paused for a short while. Please try again later.', 'corex');

        if (! $notice->hasBackBy() || $notice->isLate(time())) {
            return $text;
        }

        return $text . ' ' . sprintf(
            /* translators: %s: a date and time in the site's format. */
            __('Back by %s.', 'corex'),
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $notice->backBy),
        );
    }

    /** The seconds a refused client is told to wait. */
    public function retryAfter(string $mode): int
    {
        return $this->notice($mode)->retryAfter(time());
    }
}
