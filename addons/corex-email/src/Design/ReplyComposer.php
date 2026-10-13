<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Email\Language\EmailLanguage;

/**
 * The one place a reply becomes an email: the message in the site's design, and the same words
 * as plain text (spec 106, FR-005 and FR-013).
 *
 * One place, so that what is previewed and what is sent are the same document.
 *
 * It is drawn in the email's language, not in the language of whoever answers: the brand's
 * direction, the design's own words and the sign-off are all read inside it.
 */
final readonly class ReplyComposer
{
    public function __construct(
        private EmailDesign $design,
        private EmailBrandSource $site,
        private EmailLanguage $language,
    ) {
    }

    /**
     * @param string $bodyHtml The message, already reduced to a reply's formatting.
     */
    public function compose(string $recipient, string $subject, string $bodyHtml): ComposedEmail
    {
        return $this->language->during(function () use ($recipient, $subject, $bodyHtml): ComposedEmail {
            $brand = $this->site->brand();

            return new ComposedEmail(
                $this->design->render(new EmailContent($subject, $bodyHtml, $recipient), $brand),
                PlainText::of($bodyHtml) . "\n\n-- \n" . $this->signature($brand),
            );
        });
    }

    /** The footer's words, for the plain-text version. */
    private function signature(EmailBrand $brand): string
    {
        if ($brand->footer !== '') {
            return $brand->footer;
        }

        /* translators: 1: the name of the site an email was sent from. 2: the site's address. */
        return sprintf(__('This message was sent by %1$s (%2$s).', 'corex'), $brand->name, $brand->homeUrl);
    }
}
