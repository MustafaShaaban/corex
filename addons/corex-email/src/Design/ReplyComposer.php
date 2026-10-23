<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Mail\EmailLanguage;

/**
 * The one place a reply becomes an email: the message in the site's frame, and the same words
 * as plain text (spec 106, FR-005 and FR-013; spec 120, story 3).
 *
 * One place, so that what is previewed and what is sent are the same document.
 *
 * It is drawn in the email's language, not in the language of whoever answers: the brand's
 * direction, the design's own words and the sign-off are all read inside it.
 */
final readonly class ReplyComposer
{
    public function __construct(
        private EmailFrame $frame,
        private EmailLanguage $language,
    ) {
    }

    /**
     * @param string $bodyHtml The message, already reduced to a reply's formatting.
     */
    public function compose(string $recipient, string $subject, string $bodyHtml): ComposedEmail
    {
        return $this->language->during(fn (): ComposedEmail => new ComposedEmail(
            $this->frame->draw(new EmailContent($subject, $bodyHtml, $recipient, EmailKind::REPLY)),
            PlainText::of($bodyHtml) . "\n\n" . $this->frame->plainFooter(EmailKind::REPLY),
        ));
    }
}
