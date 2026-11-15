<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Knowledge;

defined('ABSPATH') || exit;

/**
 * What the assistant knows, in one language (spec 129, FR-012): a site's own words and its pages with
 * their addresses. Nothing else: the assistant answers from this or says it does not know.
 *
 * Also the list of pages it may link to, by address, with the name a link chip shows. Markdown links to
 * this site written inside the lines (`[Book a call](https://example.com/book/)`) are links it may give too.
 */
final class Knowledge
{
    /**
     * @param string                                  $locale the language this knowledge is written in
     * @param string                                  $site   the site's name, the knowledge's title
     * @param list<string>                            $lines  what the site says about itself, Markdown allowed
     * @param list<array{label: string, url: string}> $pages  the pages the assistant may point to
     */
    public function __construct(
        public readonly string $locale,
        public readonly string $site,
        private readonly array $lines,
        private readonly array $pages,
    ) {
    }

    public function text(): string
    {
        $lines = ['# ' . $this->site, '', ...$this->lines];
        if ($this->pages !== []) {
            $lines = [...$lines, '', '## Pages', ''];
            foreach ($this->pages as $page) {
                $lines[] = '- ' . $page['label'] . ': ' . $page['url'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Every address the knowledge holds (its pages, and the links inside its lines), with a name.
     *
     * @return array<string, string> address in {@see normal()} form => label
     */
    public function links(): array
    {
        $links = [];
        foreach ($this->lines as $line) {
            if (preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', $line, $found, PREG_SET_ORDER) > 0) {
                foreach ($found as $match) {
                    $links[self::normal($match[2])] = $match[1];
                }
            }
        }
        foreach ($this->pages as $page) {
            $links[self::normal($page['url'])] = $page['label'];
        }

        return $links;
    }

    /** An address in the one form lists and replies are compared in: no `www.`, no trailing slash. */
    public static function normal(string $url): string
    {
        return rtrim(str_replace('://www.', '://', $url), '/');
    }
}
