<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Removes what Email Studio keeps of one attempt to send an email.
 *
 * Apart from {@see EmailStudioStore}, which creates, updates and finds: attempts and captures are
 * written once and never changed, and the only thing that removes one is the deletion of the
 * submission it was sent for (spec 105, FR-011).
 */
interface EmailAttemptRemoval
{
    /**
     * Remove the record of an attempt, any attempt made again from it, and any captured copy of
     * their messages.
     *
     * @return int How many records were removed.
     */
    public function forgetAttempt(string $attemptId): int;
}
