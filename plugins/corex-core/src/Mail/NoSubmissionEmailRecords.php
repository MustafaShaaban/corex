<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * The email records of a site without the email add-on: there are none.
 */
final class NoSubmissionEmailRecords implements SubmissionEmailRecords
{
    public function forget(array $attemptIds): void
    {
    }
}
