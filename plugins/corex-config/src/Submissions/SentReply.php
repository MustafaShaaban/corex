<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;

/**
 * What became of a reply: what the mail system did with it, and whether it is on the submission.
 *
 * The two are apart because the second can fail after the first has happened. An email that
 * went and could not then be written down has still gone: answering that with an error is how
 * a customer gets the same reply twice.
 */
final readonly class SentReply
{
    public function __construct(
        public MailResult $result,
        public bool $recorded,
    ) {
    }
}
