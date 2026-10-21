<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;

/**
 * An email of a submission sent again, and to whom (spec 112, FR-034).
 */
final readonly class ResentEmail
{
    /**
     * @param list<string> $recipients Who it went to, in full: the inbox shows the person handling it.
     * @param string       $basis      {@see SubmissionEmailService::BASIS_RECORDED} or `BASIS_RECHECKED`.
     */
    public function __construct(
        public MailResult $result,
        public array $recipients,
        public string $basis,
    ) {
    }
}
