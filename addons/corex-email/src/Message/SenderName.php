<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Message;

defined('ABSPATH') || exit;

/**
 * A sender's name as it may stand in a From header, in front of the address.
 *
 * One rule, in one place. A From header is written `Name <address>` and WordPress takes it
 * apart at the first angle bracket; a header ends at a line's end; and a mail program reads
 * `=?utf-8?B?...?=` as words in another alphabet, which is how a name can be made to show as
 * something it was not written as. So a name is one line, with `<`, `>` and `"` taken out and
 * no such sequence begun, and no longer than a name is.
 *
 * It was cleaned as a form field is, by `sanitize_text_field()`, which is a rule for text on a
 * page: it wrote "Tom & Jerry <3" as "Tom & Jerry &lt;3" and took the "%ae" out of
 * "Save 50%ae today".
 */
final class SenderName
{
    /** Longer than this is not a name, and a header's line has an end. */
    private const LONGEST = 100;

    /** @return string The name, or '' where what was written holds none. */
    public static function of(string $written): string
    {
        $name = mb_check_encoding($written, 'UTF-8') ? $written : mb_scrub($written, 'UTF-8');
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name);
        $name = str_replace(['<', '>', '"', '=?'], '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return trim(mb_substr($name, 0, self::LONGEST));
    }
}
