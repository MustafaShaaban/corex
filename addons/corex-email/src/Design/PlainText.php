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
 */
final class PlainText
{
    public static function of(string $html): string
    {
        // Every pattern reads the text as UTF-8 (`u`). Read as bytes, `\R` takes the second byte
        // of an Arabic letter for a line ending and `\s` may take one for a space: the first
        // version did, and what it wrote for an Arabic reply was not text any more.
        $text = (string) preg_replace('/\s*\R\s*/u', ' ', $html);
        $text = (string) preg_replace_callback('#<ol\b[^>]*>(.*?)</ol>#isu', self::numbered(...), $text);
        $text = (string) preg_replace('#<li\b[^>]*>(.*?)</li>#isu', "- $1\n", $text);
        $text = (string) preg_replace_callback('#<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)</a>#isu', self::link(...), $text);
        $text = (string) preg_replace('#<br\s*/?>\s*#iu', "\n", $text);
        $text = (string) preg_replace('#</(p|ul|ol)>\s*#iu', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = (string) preg_replace('/[ \t]+\n/u', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $text));
    }

    /** @param array<int,string> $list */
    private static function numbered(array $list): string
    {
        $number = 0;

        return (string) preg_replace_callback(
            '#<li\b[^>]*>(.*?)</li>#isu',
            static function (array $item) use (&$number): string {
                return ++$number . '. ' . $item[1] . "\n";
            },
            $list[1],
        ) . "\n";
    }

    /**
     * A link's text, with where it goes beside it. Not twice where the text is the address.
     *
     * @param array<int,string> $link
     */
    private static function link(array $link): string
    {
        $address = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(strip_tags($link[2]));
        $bare = (string) preg_replace('#^(mailto:|tel:|https?://)#i', '', $address);

        return $text === '' || $text === $address || $text === $bare
            ? ($text === '' ? $address : $text)
            : sprintf('%s (%s)', $link[2], $address);
    }
}
