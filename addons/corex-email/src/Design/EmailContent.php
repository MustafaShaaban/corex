<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * What one email says, handed to a design to be drawn (spec 106, FR-016).
 */
final readonly class EmailContent
{
    /**
     * @param string $subject   Plain text, not escaped.
     * @param string $bodyHtml  The message, already reduced to paragraphs, line breaks,
     *                          emphasis, lists and links. A design does not have to clean it.
     * @param string $recipient The address the email is for.
     */
    public function __construct(
        public string $subject,
        public string $bodyHtml,
        public string $recipient,
    ) {
    }
}
