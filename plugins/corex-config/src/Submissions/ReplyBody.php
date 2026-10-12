<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Reduces what was posted as a reply to the formatting a reply has (spec 106, FR-004).
 *
 * A reply has paragraphs, line breaks, emphasis, lists and links. Everything else that arrives,
 * from a paste, from another client of the route or from somebody trying, is removed here, on
 * the server, before the reply is stored, previewed or sent. Its words are kept: the person sees
 * what remains. Colours, fonts and images belong to the email's template, so that every reply
 * from a site looks like that site.
 *
 * The order matters, and each step is here because a test met what happens without it:
 *
 * 1. Comments, scripts and style sheets go with what is inside them. The allowlist alone leaves
 *    a script's source standing in the message as text, and lets a comment through whole: one
 *    unclosed `<!--` hid the rest of a reply from the person it was sent to.
 * 2. A `<` that does not begin a tag is text: "Sam <sam@example.com>" and "<500 EGP" are what
 *    people type in a reply, and the allowlist deleted them as if they were markup.
 * 3. A block a reply does not have (a `div`, a heading, a table's cell) ends a paragraph where
 *    it begins and ends, and a line end inside markup is read as a space. Before the
 *    allowlist, which takes those tags and would leave their lines run together.
 * 4. The allowlist.
 * 5. Every link is read again from what the allowlist wrote, and kept only if it goes to the
 *    web, a mailbox or a telephone. Read afterwards, not before: WordPress's two readers of
 *    markup disagree about what is a tag inside `<xmp>` or `<textarea>`, and the allowlist
 *    answers a refused scheme by deleting the scheme and keeping the rest as the address, so
 *    `javascript:alert(1)` was left as a link to `alert(1)`.
 * 6. Paragraphs are made of what is left, no tag is left open, and nothing is left empty.
 */
final class ReplyBody
{
    /** @var array<string,array<string,bool>> */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'a' => ['href' => true],
    ];

    /**
     * Blocks a reply does not have. Each ends a paragraph where it begins and where it ends:
     * the allowlist takes the tags, and without this two lines run together as one. The same
     * list as the editor's (`replyHtml.js`), so that both ends read a paste the same way.
     */
    private const A_BLOCK_A_REPLY_DOES_NOT_HAVE = '#</?(?:div|h[1-6]|blockquote|section|article|header|footer|aside|main|nav'
        . '|address|figure|figcaption|center|form|fieldset|details|summary|table|caption|tr|td|th|dl|dt|dd|hr)\b[^<>]*>#i';

    /** A reply that is markup, where a line end is laid-out source and not a line somebody typed. */
    private const MARKUP_OF_ITS_OWN = '#<(?:p|br|ul|ol|li|div|h[1-6]|blockquote|section|article|pre|table|tr|td)\b#i';

    /** A reply is a message somebody wrote. Beyond this it is something else, and is not read. */
    private const MOST_BYTES = 200_000;

    /**
     * Whether what was posted is beyond what is read as a reply. Such a message is reduced to
     * nothing, which a route that keeps what it is given must not take for an emptied box.
     */
    public static function isTooLong(mixed $posted): bool
    {
        return is_string($posted) && strlen($posted) > self::MOST_BYTES;
    }

    /**
     * @param mixed $posted Whatever the route was sent as the message.
     *
     * @return string The reduced markup, or '' when no words are left in it.
     */
    public static function reduce(mixed $posted): string
    {
        if (! is_string($posted) || strlen($posted) > self::MOST_BYTES) {
            return '';
        }
        // Bytes that are not text are replaced first: every reader after this one expects text.
        $markup = (string) preg_replace('#<!--.*?(?:-->|$)#s', '', mb_scrub($posted, 'UTF-8'));
        $markup = (string) preg_replace('#<(script|style)\b[^>]*>.*?(?:</\1\s*>|$)#is', '', $markup);
        $markup = (string) preg_replace('#<(?!/?[a-z][a-z0-9]*(?:\s[^<>]*)?/?>)#i', '&lt;', $markup);
        $markup = self::withBlocksAsParagraphs($markup);
        $markup = wp_kses($markup, self::ALLOWED, ['http', 'https', 'mailto', 'tel']);
        $markup = self::withLinksThatGoSomewhere($markup);
        $markup = self::whole(wpautop(trim($markup)));

        return self::hasWords($markup) ? $markup : '';
    }

    /**
     * Ends a paragraph wherever a block a reply does not have begins or ends.
     *
     * Where the reply is markup, a line end in it is first read as the space a browser draws:
     * the next reader takes every line end for a line break, and a paragraph pasted from a
     * word processor, which wraps its markup, was broken in the middle of its sentences. Where
     * it is lines somebody typed, with no markup of their own, the line ends are theirs and stay.
     */
    private static function withBlocksAsParagraphs(string $markup): string
    {
        if (preg_match(self::MARKUP_OF_ITS_OWN, $markup) === 1) {
            $markup = (string) preg_replace_callback(
                '#<pre\b[^>]*>(.*?)</pre\s*>#is',
                static fn (array $pre): string => '<p>' . str_replace(["\r\n", "\r", "\n"], '<br>', trim($pre[1], "\r\n")) . '</p>',
                $markup,
            );
            $markup = (string) preg_replace('/[ \t]*[\r\n]+[ \t]*/', ' ', $markup);
        }

        return (string) preg_replace(self::A_BLOCK_A_REPLY_DOES_NOT_HAVE, "\n\n", $markup);
    }

    /**
     * Leaves no tag open, and no paragraph or emphasis with nothing in it.
     *
     * Emphasis written around a block is cut in two when the block becomes a paragraph. The
     * editor moves it inside before posting; this is for whatever posts without it.
     */
    private static function whole(string $markup): string
    {
        $markup = force_balance_tags($markup);
        do {
            $markup = (string) preg_replace('#<(strong|b|em|i|p|li|ul|ol)>\s*</\1>#i', '', $markup, -1, $removed);
        } while ($removed > 0);

        return trim($markup);
    }

    /**
     * Keeps a link that goes to the web, a mailbox or a telephone, gives one with no scheme
     * the scheme it meant, and leaves any other as its text.
     *
     * Read from the allowlist's own output, where an address is always `href="…"`.
     */
    private static function withLinksThatGoSomewhere(string $markup): string
    {
        // A kept link is written under a name the allowlist let nothing through with, so that
        // what is left under `a` afterwards is known to be a link with no address, or half of one.
        $markup = (string) preg_replace_callback(
            '#<a\s+href="([^"]*)"\s*>(.*?)</a>#is',
            static function (array $link): string {
                $address = self::address(html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $address = $address === '' ? '' : esc_url($address, ['http', 'https', 'mailto', 'tel']);

                return $address === ''
                    ? $link[2]
                    : sprintf('<kept-link href="%s">%s</kept-link>', $address, $link[2]);
            },
            $markup,
        );
        $markup = (string) preg_replace('#</?a\b[^>]*>#i', '', $markup);

        return str_replace(['<kept-link ', '</kept-link>'], ['<a ', '</a>'], $markup);
    }

    /** @return string The address a link may keep, or '' when it may keep none. */
    private static function address(string $written): string
    {
        $address = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $written));
        if ($address === '') {
            return '';
        }
        if (preg_match('#^(https?://|mailto:|tel:)\S#i', $address) === 1) {
            return str_replace(' ', '%20', $address);
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $address) === 1) {
            return '';
        }
        if (is_email((string) strtok($address, '?')) !== false) {
            return 'mailto:' . $address;
        }
        $address = ltrim($address, '/');
        $host = (string) parse_url('https://' . $address, PHP_URL_HOST);

        // "pricing" and "#top" are places on a page the reader is not on.
        return str_contains($host, '.') ? 'https://' . str_replace(' ', '%20', $address) : '';
    }

    private static function hasWords(string $markup): bool
    {
        $text = html_entity_decode(wp_strip_all_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) !== '';
    }
}
