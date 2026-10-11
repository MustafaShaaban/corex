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
 * not depend on which plugin booted first. The filter is handed CoreX's standard design: a
 * design that wants to build on it wraps that one, not the container's.
 *
 * A site's design is code CoreX did not write, running at the moment a customer is being
 * answered. Everything of the site's is inside the guard: the filter's own callback, the
 * drawing, and anything the design prints instead of returning. If any of it throws, answers
 * nothing, or answers a document without the message, the email goes in the standard design
 * and the failure is announced. An email is never sent without its words, and a broken
 * template never stops a reply.
 */
final class SiteEmailDesign implements EmailDesign
{
    public const FILTER = 'corex_email_design';

    /** As much of an error's own words as is passed on: one line, for a notification. */
    private const MOST_DETAIL = 200;

    /** True while a site's design is drawing, so that one which asks for this class again gets the standard design and not itself. */
    private bool $drawing = false;

    public function __construct(
        private readonly StandardEmailDesign $standard,
        private readonly EventDispatcher $events,
    ) {
    }

    public function render(EmailContent $content, EmailBrand $brand): string
    {
        if ($this->drawing) {
            return $this->standard->render($content, $brand);
        }

        $this->drawing = true;
        $design = self::FILTER;
        // What a design prints is taken off the response it would otherwise be written into.
        ob_start();
        $buffer = ob_get_level();

        try {
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
            $design = $this->named($given);
            $html = $given->render($content, $brand);
        } catch (Throwable $error) {
            return $this->standardAfter($content, $brand, $design, EmailDesignFailedEvent::THREW, $this->said($error));
        } finally {
            // Ours, and any a design opened and left open above it.
            while (ob_get_level() >= $buffer) {
                ob_end_clean();
            }
            $this->drawing = false;
        }

        if (trim($html) === '') {
            return $this->standardAfter($content, $brand, $design, EmailDesignFailedEvent::EMPTY);
        }
        if (! $this->holds($html, $content->bodyHtml)) {
            return $this->standardAfter($content, $brand, $design, EmailDesignFailedEvent::WITHOUT_MESSAGE);
        }

        return $html;
    }

    /** The design's class, to find it by. An anonymous class is named up to where PHP's own name stops being text. */
    private function named(EmailDesign $design): string
    {
        return (string) strtok($design::class, "\0");
    }

    /**
     * What an error said, as one short line with its kind: enough to find it by, and no more of
     * a stranger's error message than that in front of everybody who manages email.
     */
    private function said(Throwable $error): string
    {
        $kind = (string) strrchr('\\' . $error::class, '\\');
        $words = trim((string) strtok(strip_tags($error->getMessage()), "\r\n"));

        return mb_substr(ltrim($kind, '\\') . ($words === '' ? '' : ': ' . $words), 0, self::MOST_DETAIL);
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
     * Whether a document holds the message: its words, whole and in order, in what the document
     * shows.
     *
     * Words, not markup, so that a design is free to restyle a paragraph or a link. In what it
     * shows, so that a title, a style sheet or a comment that happens to repeat a word does not
     * count. As whole words, so that "Thanks" is not found in "Thanks for writing to us" only
     * because the letters are there: it is, as a word, and "Yes" is not found in "Yesterday".
     */
    private function holds(string $document, string $message): bool
    {
        $said = $this->words($message);
        if ($said === []) {
            return true;
        }
        $shown = $this->words((string) preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1\s*>#isu', ' ', mb_scrub($document, 'UTF-8')));

        return str_contains(' ' . implode(' ', $shown) . ' ', ' ' . implode(' ', $said) . ' ');
    }

    /**
     * The words of some markup, in order.
     *
     * Only what is a tag is taken out: a `<` that begins none, as in a style rule or "we <3
     * you", is text, and a reader that took it for a tag would lose everything after it.
     *
     * @return list<string>
     */
    private function words(string $html): array
    {
        $text = (string) preg_replace('#<!--.*?-->|</?[a-z][a-z0-9]*\b[^<>]*>#isu', ' ', mb_scrub($html, 'UTF-8'));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/[\s\x{00A0}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }
}
