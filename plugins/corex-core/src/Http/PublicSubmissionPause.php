<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use DateTimeImmutable;

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
 *
 * What a visitor is told is the owner's message for the mode when one is set ({@see VisitorMessages},
 * filled by spec 124's slice 9), else CoreX's own, so the refusal and the paused panel of a flow
 * block (FR-021) say the same words.
 */
final class PublicSubmissionPause
{
    public const MAINTENANCE = 'maintenance';

    public const FORMS_PAUSED = 'forms-paused';

    /** What a client is told to wait, in seconds, until the back-by time of spec 124's slice 9. */
    private const RETRY_AFTER_SECONDS = 3600;

    public function __construct(private readonly VisitorMessages $messages = new VisitorMessages())
    {
    }

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
        $owner = trim($this->messages->for($mode)->message);
        if ($owner !== '') {
            return $owner;
        }

        return $mode === self::MAINTENANCE
            ? __('The site is closed for maintenance and is not taking submissions. Please try again later.', 'corex')
            : __('Forms are paused for a short while. Please try again later.', 'corex');
    }

    /**
     * The paused panel's words (spec 124, FR-021): its title, the message, and the line saying when
     * forms are back. A back-by time that has passed is never shown, as on the maintenance page;
     * with none set the line is empty, and nothing is drawn for it.
     *
     * @return array{title:string,message:string,when:string}
     */
    public function pausedNotice(DateTimeImmutable $now): array
    {
        return [
            'title' => _x('Paused', 'flow notice title', 'corex'),
            'message' => $this->message(self::FORMS_PAUSED),
            'when' => $this->backBy($this->messages->for(self::FORMS_PAUSED)->backBy, $now),
        ];
    }

    /** The seconds a refused client is told to wait. */
    public function retryAfter(): int
    {
        return self::RETRY_AFTER_SECONDS;
    }

    /** When forms are back, in the site's own date and time formats and time zone; '' for no time. */
    private function backBy(?DateTimeImmutable $at, DateTimeImmutable $now): string
    {
        if ($at === null) {
            return '';
        }
        if ($at <= $now) {
            return __('This is taking longer than planned.', 'corex');
        }

        return sprintf(
            /* translators: 1: a date, 2: a time of day, both in the site's formats. */
            __('Back by %1$s at %2$s.', 'corex'),
            wp_date((string) get_option('date_format'), $at->getTimestamp()),
            wp_date((string) get_option('time_format'), $at->getTimestamp()),
        );
    }
}
