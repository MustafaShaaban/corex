<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Where a signed-in person reads one stored submission.
 *
 * An email that says a submission arrived points here, and what was sent with the submission is
 * opened from there by whoever may read it (spec 081, US3). The screen is CoreX's admin, which
 * the forms plugin does not know: whatever owns the screen answers, and a site without one has
 * no address to give.
 */
interface SubmissionAddress
{
    /**
     * @return string An absolute address in the admin. Whoever follows it has to be signed in:
     *                on a site that hides its login address, a signed-out visitor is shown
     *                "not found", not a way in.
     */
    public function of(int $submissionId): string;
}
