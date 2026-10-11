<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Throwable;

/**
 * The design this site's emails are drawn in: its own, when it gives one that works, and
 * CoreX's standard one otherwise (spec 106, FR-014 and FR-017).
 *
 * A site gives its design through the filter `corex_email_design`, from its own plugin:
 *
 *     add_filter('corex_email_design', fn () => new AcmeEmailDesign());
 *
 * A filter and not a container binding, because it is asked when an email is drawn and so does
 * not depend on which plugin booted first.
 *
 * A site's design is code CoreX did not write, running at the moment a customer is being
 * answered. If it throws, answers nothing, or answers a document without the message, the email
 * goes in the standard design and the failure is announced. An email is never sent without its
 * words, and a broken template never stops a reply.
 */
final readonly class SiteEmailDesign implements EmailDesign
{
    public const FILTER = 'corex_email_design';

    public function __construct(
        private StandardEmailDesign $standard,
        private EventDispatcher $events,
    ) {
    }

    public function render(EmailContent $content, EmailBrand $brand): string
    {
        /**
         * The design this site's emails are drawn in.
         *
         * @param EmailDesign $design CoreX's standard design. Return your own to replace it.
         */
        $given = apply_filters(self::FILTER, $this->standard);
        if ($given === $this->standard) {
            return $this->standard->render($content, $brand);
        }
        if (! $given instanceof EmailDesign) {
            return $this->standardAfter($content, $brand, get_debug_type($given), EmailDesignFailedEvent::NOT_A_DESIGN);
        }

        try {
            $html = $given->render($content, $brand);
        } catch (Throwable $error) {
            return $this->standardAfter($content, $brand, $this->named($given), EmailDesignFailedEvent::THREW, $error->getMessage());
        }
        if (trim($html) === '') {
            return $this->standardAfter($content, $brand, $this->named($given), EmailDesignFailedEvent::EMPTY);
        }
        if (! $this->holds($html, $content->bodyHtml)) {
            return $this->standardAfter($content, $brand, $this->named($given), EmailDesignFailedEvent::WITHOUT_MESSAGE);
        }

        return $html;
    }

    /** The design's class, to find it by. An anonymous class is named up to where PHP's own name stops being text. */
    private function named(EmailDesign $design): string
    {
        return (string) strtok($design::class, "\0");
    }

    private function standardAfter(
        EmailContent $content,
        EmailBrand $brand,
        string $design,
        string $reason,
        string $detail = '',
    ): string {
        $this->events->dispatch(new EmailDesignFailedEvent($design, $reason, $detail));

        return $this->standard->render($content, $brand);
    }

    /**
     * Whether a document holds the message: its words, whole and in order.
     *
     * Words, not markup, so that a design is free to restyle a paragraph or a link. Spacing is
     * left out of the comparison for the same reason.
     */
    private function holds(string $document, string $message): bool
    {
        $words = $this->words($message);

        return $words === '' || str_contains($this->words($document), $words);
    }

    private function words(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/[\s\x{00A0}]+/u', '', $text);
    }
}
