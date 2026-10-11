<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * A message written out as plain text, for the part of an email a client shows when it shows
 * no HTML (spec 106, FR-013): the same words, paragraphs apart, list items marked, and a link
 * with its address beside it.
 *
 * It reads a message that was already reduced to a reply's formatting. Anything else that
 * reaches it loses its tags and keeps its words.
 *
 * Every pattern reads the text as UTF-8 (`u`). Read as bytes, `\R` takes the second byte of an
 * Arabic letter for a line ending: the first version did, and what it wrote for an Arabic reply
 * was not text any more. Bytes that are not UTF-8 are replaced before anything is matched,
 * because a `u` pattern answers nothing at all for them and the email would leave with its
 * plain-text part empty.
 */
final class PlainText
{
    /** A list with no list inside it: the innermost are written out first. */
    private const INNERMOST_LIST = '#<(ul|ol)\b[^>]*>((?:(?!<(?:ul|ol)\b).)*?)</\1>#isu';

    private const INDENT = '  ';

    public static function of(string $html): string
    {
        $text = mb_scrub($html, 'UTF-8');
        // A line ending between two tags is how the markup was laid out, not something said.
        // One inside a sentence is a space.
        $text = (string) preg_replace('/>\s*\R\s*</u', '><', $text);
        $text = (string) preg_replace('/\s*\R\s*/u', ' ', $text);
        $text = (string) preg_replace_callback('#<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)</a>#isu', self::link(...), $text);
        $text = (string) preg_replace('#<br\s*/?>\s*#iu', "\n", $text);
        while (preg_match(self::INNERMOST_LIST, $text) === 1) {
            $text = (string) preg_replace_callback(self::INNERMOST_LIST, self::listed(...), $text, 1);
        }
        $text = (string) preg_replace('#</p>\s*#iu', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = (string) preg_replace('/[ \t]+\n/u', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $text));
    }

    /**
     * One list as lines: "- " before each item of a bulleted one, its number before each of a
     * numbered one. A list that was inside the item is already lines, and is set in under it.
     *
     * @param array<int,string> $list The list's tag name, then what is between its tags.
     */
    private static function listed(array $list): string
    {
        $numbered = strtolower($list[1]) === 'ol';
        $number = 0;
        $lines = [];
        preg_match_all('#<li\b[^>]*>(.*?)</li>#isu', $list[2], $items);
        foreach ($items[1] as $item) {
            $mark = $numbered ? ++$number . '. ' : '- ';
            $words = (string) preg_replace('/\n{2,}/u', "\n", trim($item, " \n"));
            $lines[] = $mark . str_replace("\n", "\n" . self::INDENT, $words);
        }

        // Apart from what is before and after it, whether that is a paragraph or an item's words.
        return "\n\n" . implode("\n", $lines) . "\n\n";
    }

    /**
     * A link's text, with where it goes beside it. Not twice where the text is the address.
     *
     * @param array<int,string> $link The link's address, then what is between its tags.
     */
    private static function link(array $link): string
    {
        $address = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(html_entity_decode(strip_tags($link[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $bare = (string) preg_replace('#^(mailto:|tel:|https?://)#i', '', $address);
        if ($text === '') {
            return htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
        }

        return in_array($text, [$address, $bare], true)
            ? $link[2]
            : sprintf('%s (%s)', $link[2], htmlspecialchars($address, ENT_QUOTES, 'UTF-8'));
    }
}
