<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * A subject as an email carries it: plain text, on one line.
 *
 * A subject is a header, not markup. Cleaned as the text of an HTML form it reached a customer
 * changed: "Price <500 EGP" as "Price &lt;500 EGP", "Sam <sam@example.com> asked" as
 * "Sam asked", "Save 50%de today" as "Save 50 today". So what was typed is kept as typed, and
 * whoever prints a subject into markup escapes it there.
 *
 * What a header cannot hold is a line break: whatever followed one would be read as a header of
 * its own. A break, or any other control character, becomes a space.
 */
final class SubjectLine
{
    /** A subject is a line. Beyond this it is something pasted by mistake, and is cut. */
    public const LONGEST = 300;

    /**
     * Control characters, with the two separators Unicode has for a line and a paragraph.
     * `\p{Cc}` is all of them: a tab, a line break, a null byte, the delete character and the
     * ones above it (U+0080 to U+009F), which a byte range written for ASCII does not reach.
     */
    private const BREAKS = '\p{Cc}\x{2028}\x{2029}';

    /**
     * @param mixed $typed What was posted as a subject.
     *
     * @return string One line of at most {@see LONGEST} characters; '' for what is not text,
     *                or is not UTF-8.
     */
    public static function of(mixed $typed): string
    {
        if (! is_string($typed)) {
            return '';
        }
        // The spaces round a break go with it: a second line pasted with its indent is one
        // space away from the first, not five.
        $line = preg_replace('/ *[' . self::BREAKS . '][ ' . self::BREAKS . ']*/u', ' ', $typed);
        // Read as UTF-8 (`u`), text that is not UTF-8 is not read at all: there is no answer.
        if ($line === null) {
            return '';
        }

        return rtrim(mb_substr(trim($line), 0, self::LONGEST));
    }
}
