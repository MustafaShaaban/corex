<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Reply;

defined('ABSPATH') || exit;

use Corex\Assistant\Knowledge\Knowledge;

/**
 * Turns a model's text into what the chat shows (spec 129, FR-010): the reply, whether to offer the
 * team, and up to two link chips.
 *
 * Only this site's pages that the knowledge names become chips; every other address is removed, so a
 * reply can never link a visitor elsewhere, or to a page of this site it made up. Email addresses and
 * international phone numbers are removed too. A bare domain written as a word is not: it cannot be
 * told from a product name, and it is not a link. Markdown the model was told not to use is undone.
 */
final class ReplyShaper
{
    private const MAX_LINKS = 2;

    /** Punctuation a sentence puts after an address, which is not part of it. */
    private const TRAILING = '.,;:!?،)';

    /** Where an address was taken out, so the colon that introduced it can go too. */
    private const GAP = "\u{E000}";

    private const EMAIL ='/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu';

    /** An international number: a plus, then at least eight digits with the usual separators. */
    private const PHONE = '/\+\d[\d \-().]{6,}\d/u';

    /**
     * @param array<string, string> $known address (as {@see Knowledge::normal()}) => label
     * @return array{reply: string, team: bool, links: list<array{url: string, label: string}>}
     */
    public static function shape(string $text, string $siteHost, array $known): array
    {
        $team = stripos($text, Instructions::TEAM_TOKEN) !== false;
        $text = str_ireplace(Instructions::TEAM_TOKEN, '', $text);

        $links = [];
        $keep = static function (string $url) use (&$links, $siteHost, $known): void {
            $url = rtrim($url, self::TRAILING);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (self::bare($host) !== self::bare(strtolower($siteHost)) || count($links) >= self::MAX_LINKS) {
                return;
            }
            $normal = Knowledge::normal($url);
            foreach ($links as $link) {
                if (Knowledge::normal($link['url']) === $normal) {
                    return;
                }
            }
            // Only a page the knowledge names: a same-site address the model made up is not a chip.
            if (isset($known[$normal])) {
                $links[] = ['url' => $url, 'label' => $known[$normal]];
            }
        };

        // [label](address) → label, keeping the address as a chip.
        $text = (string) preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', static function (array $m) use ($keep): string {
            $keep($m[2]);

            return $m[1];
        }, $text);

        // Bare addresses → chips; gone from the text either way.
        $text = (string) preg_replace_callback('/<?https?:\/\/[^\s<>"\']+>?/u', static function (array $m) use ($keep): string {
            $address = trim($m[0], '<>');
            $url = rtrim($address, self::TRAILING);
            $keep($url);

            // The sentence's own punctuation, which the match swallowed, stays in the sentence.
            return self::GAP . substr($address, strlen($url));
        }, $text);

        // "See the page: <address>" reads "See the page" once the address is a chip.
        $text = (string) preg_replace('/\s*[:：]\s*' . self::GAP . '/u', '', $text);
        $text = str_replace(self::GAP, '', $text);

        // The instructions forbid them; a reply steered by a visitor's message could still carry them.
        $text = (string) preg_replace(self::EMAIL, '', $text);
        $text = (string) preg_replace(self::PHONE, '', $text);

        return ['reply' => self::tidy($text), 'team' => $team, 'links' => $links];
    }

    /** Markdown marks gone, spaces before punctuation gone, a dangling colon closed, blank runs cut. */
    private static function tidy(string $text): string
    {
        $text = (string) preg_replace('/\*\*|__|^#+\s*|`/mu', '', $text);
        $text = (string) preg_replace('/[ \t]+([.,،:;!?])/u', '$1', $text);
        $text = (string) preg_replace('/[ \t]{2,}/u', ' ', $text);
        // Spaces only: `\s` would also take the line breaks after it, and join two paragraphs.
        $text = (string) preg_replace('/:[ \t]*$/mu', '.', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function bare(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
