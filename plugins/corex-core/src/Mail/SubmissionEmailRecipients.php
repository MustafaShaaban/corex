<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Works out again who one of a submission's emails is addressed to, from the flow version the
 * submission was made with and the answers it holds (spec 112, FR-032a). For an email sent before
 * CoreX recorded its recipients; implemented by the forms plugin, which owns flows.
 */
interface SubmissionEmailRecipients
{
    /**
     * @param string $event The email's binding, e.g. `team_notification`.
     *
     * @return list<string>|null The addresses, or null when the flow or the binding is gone.
     */
    public function resolve(int $submissionId, string $event): ?array;
}
